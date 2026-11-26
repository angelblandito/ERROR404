<?php
/*
 * Comparador de mercado: nuestros precios frente a productos equivalentes
 * de otras marcas.
 *
 *   GET    /market                 Comparación por producto y por categoría (Prioritario y Administrador)
 *   GET    /admin/market           Precios registrados y competidores
 *   POST   /admin/market           {id_product, id_competitor, competitor_product, retailer, price, source_url?, captured_on}
 *   PUT    /admin/market/{id}      {price, captured_on, source_url?}
 *   DELETE /admin/market/{id}      Baja lógica
 *
 * Layer: services. Logica de negocio y SQL; sin HTTP. Las rutas viven en routers y los
 * controllers de controllers llaman a estas funciones con el actor de la sesion.
 */

function market_require_member(?array $actor): array
{
    $account = $actor;
    if ($account === null) {
        throw new HttpError(401, 'Inicia sesión con una cuenta Prioritario para ver el comparador de mercado.');
    }
    if (!store_is_priority($account)) {
        throw new HttpError(403, 'El comparador de mercado es un beneficio de las cuentas Prioritario y Administrador.');
    }
    return $account;
}

function market_offers(PDO $pdo): array
{
    $rows = store_rows($pdo, '
        SELECT mp.id_market_price, mp.id_product, mp.id_competitor, c.name AS competitor, mp.competitor_product,
               mp.retailer, mp.price, mp.source_url, mp.captured_on
        FROM dbo.market_price AS mp
        JOIN dbo.competitor AS c ON c.id_competitor = mp.id_competitor
        WHERE mp.deleted = 0
        ORDER BY mp.id_product, mp.price');

    foreach ($rows as &$r) {
        $r['id_market_price'] = (int)$r['id_market_price'];
        $r['id_product'] = (int)$r['id_product'];
        $r['id_competitor'] = (int)$r['id_competitor'];
        $r['price'] = (float)$r['price'];
    }
    unset($r);
    return $rows;
}

function market_competitors(PDO $pdo): array
{
    return array_map(function ($c) {
        return ['id_competitor' => (int)$c['id_competitor'], 'name' => $c['name'], 'website' => $c['website']];
    }, store_rows($pdo, 'SELECT id_competitor, name, website FROM dbo.competitor ORDER BY id_competitor'));
}

function market_percent(float $ours, float $theirs): float
{
    return $theirs > 0 ? round(($theirs - $ours) / $theirs * 100, 1) : 0.0;
}

function market_compare(PDO $pdo, array $account): array
{
    $tier = store_rows($pdo, '
        SELECT t.name, t.discount_percent
        FROM dbo.customer AS c
        JOIN dbo.account_tier AS t ON t.id_account_tier = c.id_account_tier
        WHERE c.id_customer = :id', [':id' => $account['id_customer']])[0];
    $discount = (float)$tier['discount_percent'];

    $products = store_rows($pdo, '
        SELECT p.id_product, p.name, p.base_price, c.name AS category, c.code AS category_code, c.department
        FROM dbo.product AS p
        JOIN dbo.category AS c ON c.id_category = p.id_category
        WHERE p.deleted = 0
        ORDER BY c.id_category, p.id_product');

    $offersByProduct = [];
    foreach (market_offers($pdo) as $offer) {
        $offersByProduct[$offer['id_product']][] = $offer;
    }

    $result = [];
    $categories = [];
    $latest = null;
    foreach ($products as $p) {
        $offers = $offersByProduct[(int)$p['id_product']] ?? [];
        if (!$offers) {
            continue;
        }
        $prices = array_column($offers, 'price');
        $ours = (float)$p['base_price'];
        $member = round($ours * (1 - $discount / 100), 2);
        $average = round(array_sum($prices) / count($prices), 2);
        foreach ($offers as $o) {
            $latest = $latest === null || $o['captured_on'] > $latest ? $o['captured_on'] : $latest;
        }

        $result[] = [
            'id_product' => (int)$p['id_product'],
            'name' => $p['name'],
            'category' => $p['category'],
            'category_code' => $p['category_code'],
            'department' => $p['department'],
            'our_price' => $ours,
            'member_price' => $member,
            'market_average' => $average,
            'market_min' => min($prices),
            'market_max' => max($prices),
            'savings_percent' => market_percent($ours, $average),
            'member_savings_percent' => market_percent($member, $average),
            'offers' => $offers,
        ];

        $key = $p['category_code'];
        if (!isset($categories[$key])) {
            $categories[$key] = ['category' => $p['category'], 'category_code' => $key, 'products' => 0,
                                 'our_total' => 0.0, 'member_total' => 0.0, 'market_total' => 0.0];
        }
        $categories[$key]['products']++;
        $categories[$key]['our_total'] += $ours;
        $categories[$key]['member_total'] += $member;
        $categories[$key]['market_total'] += $average;
    }

    $byCategory = array_map(function ($c) {
        $n = $c['products'];
        $ours = round($c['our_total'] / $n, 2);
        $member = round($c['member_total'] / $n, 2);
        $market = round($c['market_total'] / $n, 2);
        return [
            'category' => $c['category'],
            'category_code' => $c['category_code'],
            'products' => $n,
            'our_average' => $ours,
            'member_average' => $member,
            'market_average' => $market,
            'savings_percent' => market_percent($ours, $market),
            'member_savings_percent' => market_percent($member, $market),
        ];
    }, array_values($categories));

    return [
        'tier' => ['name' => $tier['name'], 'discount_percent' => $discount],
        'captured_on' => $latest,
        'competitors' => market_competitors($pdo),
        'categories' => $byCategory,
        'products' => $result,
    ];
}

/* ---------------------------------------------------------------------
   Administración de precios de mercado
   --------------------------------------------------------------------- */
function market_url(array $body): ?string
{
    $url = trim((string)($body['source_url'] ?? ''));
    if ($url === '') {
        return null;
    }
    if (strlen($url) > 300 || !preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new HttpError(400, 'La fuente debe ser una dirección web que empiece con http:// o https://');
    }
    return $url;
}

function market_price_value(array $body): string
{
    $price = round((float)($body['price'] ?? 0), 2);
    if ($price < 1 || $price > 999999) {
        throw new HttpError(400, 'El precio debe estar entre $1 y $999,999.');
    }
    return number_format($price, 2, '.', '');
}

function market_create(PDO $pdo, array $body): array
{
    $stmt = $pdo->prepare("
        INSERT INTO dbo.market_price (id_product, id_competitor, competitor_product, retailer, price, source_url, captured_on, source_node)
        SELECT p.id_product, c.id_competitor, :competitor_product, :retailer, :price, :source_url, :captured_on, 'web_node'
        FROM dbo.product AS p
        CROSS JOIN dbo.competitor AS c
        WHERE p.id_product = :id_product AND p.deleted = 0 AND c.id_competitor = :id_competitor");
    $url = market_url($body);
    $stmt->bindValue(':competitor_product', store_text($body, 'competitor_product', 3, 100, 'El producto de la competencia'), PDO::PARAM_STR);
    $stmt->bindValue(':retailer', store_text($body, 'retailer', 2, 40, 'La tienda'), PDO::PARAM_STR);
    $stmt->bindValue(':price', market_price_value($body), PDO::PARAM_STR);
    $stmt->bindValue(':source_url', $url, $url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':captured_on', admin_date($body, 'captured_on', true), PDO::PARAM_STR);
    $stmt->bindValue(':id_product', (int)($body['id_product'] ?? 0), PDO::PARAM_INT);
    $stmt->bindValue(':id_competitor', (int)($body['id_competitor'] ?? 0), PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        throw new HttpError(400, 'El producto o la marca no existen.');
    }
    return ['id_market_price' => (int)$pdo->lastInsertId()];
}

function market_update(PDO $pdo, int $id, array $body): array
{
    $url = market_url($body);
    $stmt = $pdo->prepare('
        UPDATE dbo.market_price
        SET price = :price, captured_on = :captured_on, source_url = COALESCE(:source_url, source_url)
        WHERE id_market_price = :id AND deleted = 0');
    $stmt->bindValue(':price', market_price_value($body), PDO::PARAM_STR);
    $stmt->bindValue(':captured_on', admin_date($body, 'captured_on', true), PDO::PARAM_STR);
    $stmt->bindValue(':source_url', $url, $url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        throw new HttpError(404, 'El precio de mercado no existe.');
    }
    return ['id_market_price' => $id];
}

function market_delete(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('UPDATE dbo.market_price SET deleted = 1 WHERE id_market_price = :id AND deleted = 0');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        throw new HttpError(404, 'El precio de mercado no existe.');
    }
    return ['id_market_price' => $id];
}

