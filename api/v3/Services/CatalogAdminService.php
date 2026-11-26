<?php
declare(strict_types=1);

namespace Apicore\Services;

use Apicore\Core\AppError;
use PDO;
use Throwable;

/**
 * Admin panel: product photos and seasons.
 *
 *   POST /admin/products/{id}/photo   {"image": "data:image/jpeg;base64,...", "color": "black"}
 *       Without "color" the photo belongs to the product (img/productos/<product-name>.jpg) and is
 *       used for every color; with "color" only for that color (img/productos/<id>-<color>.jpg).
 *   DELETE /admin/products/{id}/photo?color=black
 *   PUT  /admin/products/{id}/seasons {"seasons": ["winter", "christmas"]}
 *       Leaves the product in exactly those seasons (table dbo.product_season).
 * Layer: services.
 */
final class CatalogAdminService
{
    public const PHOTO_MAX_BYTES = 700 * 1024;       /* the request body allows 1 MB; base64 grows 33 % */

    public const PHOTO_MAX_SIDE = 4000;

    public const PHOTO_TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public static function adminProduct(PDO $pdo, int $idProduct): array
    {
        $rows = StoreService::rows($pdo, 'SELECT id_product, name FROM dbo.product WHERE id_product = :id AND deleted = 0', [':id' => $idProduct]);
        if (!$rows) {
            throw new AppError('El producto no existe.', 404);
        }
        return $rows[0];
    }

    /* "Balón de fútbol JAM Strike No. 5" -> "balon-de-futbol-jam-strike-no-5" (same as photoName() in the page) */
    public static function photoSlug(string $name): string
    {
        $text = mb_strtolower($name, 'UTF-8');
        if (class_exists('Normalizer')) {
            $text = (string)\Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = (string)preg_replace('/\p{Mn}+/u', '', $text);
        } else {
            $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        }
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    }

    /* Base file name: <id>-<color> or <product-name>. */
    public static function photoBase(PDO $pdo, array $product, ?string $color): string
    {
        if ($color === null || $color === '') {
            return self::photoSlug($product['name']);
        }
        if (!preg_match('/^[a-z]{2,5}$/', $color)
            || !StoreService::rows($pdo, 'SELECT 1 AS ok FROM dbo.color WHERE code = :code', [':code' => $color])) {
            throw new AppError('El color no existe.', 400);
        }
        return (int)$product['id_product'] . '-' . $color;
    }

    public static function removePhotos(string $base): int
    {
        $removed = 0;
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $file = StoreService::PHOTO_FOLDER . '/' . $base . '.' . $ext;
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    public static function savePhoto(PDO $pdo, int $idProduct, array $body): array
    {
        $product = self::adminProduct($pdo, $idProduct);
        $color = isset($body['color']) ? strtolower(trim(StoreService::field($body, 'color'))) : null;
        $base = self::photoBase($pdo, $product, $color);

        $image = StoreService::field($body, 'image', '');
        if (preg_match('/^data:image\/[a-z]+;base64,/', $image)) {
            $image = substr($image, strpos($image, ',') + 1);
        }
        $bytes = base64_decode($image, true);
        if ($bytes === false || $bytes === '') {
            throw new AppError('Manda la foto en base64 en el campo "image".', 400);
        }
        if (strlen($bytes) > self::PHOTO_MAX_BYTES) {
            throw new AppError('La foto pesa más de ' . (self::PHOTO_MAX_BYTES / 1024) . ' KB. Redúcela antes de subirla.', 400);
        }
        /* The type comes from the content, never from the name or what the browser says */
        $info = @getimagesizefromstring($bytes);
        if ($info === false || !isset(self::PHOTO_TYPES[$info[2]])) {
            throw new AppError('La foto debe ser JPG, PNG o WebP.', 400);
        }
        if ($info[0] < 100 || $info[1] < 100 || $info[0] > self::PHOTO_MAX_SIDE || $info[1] > self::PHOTO_MAX_SIDE) {
            throw new AppError('La foto debe medir entre 100 y ' . self::PHOTO_MAX_SIDE . ' pixeles por lado.', 400);
        }

        if (!is_dir(StoreService::PHOTO_FOLDER) || !is_writable(StoreService::PHOTO_FOLDER)) {
            throw new AppError('El servidor no puede escribir en la carpeta de fotos.', 500);
        }
        $file = $base . '.' . self::PHOTO_TYPES[$info[2]];
        $temporary = StoreService::PHOTO_FOLDER . '/.subiendo-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $bytes) === false) {
            throw new AppError('No se pudo guardar la foto.', 500);
        }
        /* One photo per name: if there was a .png and a .jpg arrives, the old one is removed */
        self::removePhotos($base);
        if (!rename($temporary, StoreService::PHOTO_FOLDER . '/' . $file)) {
            @unlink($temporary);
            throw new AppError('No se pudo guardar la foto.', 500);
        }
        return ['id_product' => $idProduct, 'file' => $file, 'width' => $info[0], 'height' => $info[1], 'bytes' => strlen($bytes)];
    }

    public static function deletePhoto(PDO $pdo, int $idProduct, ?string $color): array
    {
        $product = self::adminProduct($pdo, $idProduct);
        $base = self::photoBase($pdo, $product, $color === null ? null : strtolower($color));
        if (self::removePhotos($base) === 0) {
            throw new AppError('El producto no tiene esa foto.', 404);
        }
        return ['id_product' => $idProduct, 'removed' => $base];
    }

    public static function setSeasons(PDO $pdo, int $idProduct, array $body): array
    {
        self::adminProduct($pdo, $idProduct);
        if (!StoreService::productSeasonTable($pdo)) {
            throw new AppError('Falta la tabla de temporadas. Ejecuta API_JAM/distribuida/08_catalogo_temporadas.sql.', 409);
        }
        $codes = $body['seasons'] ?? null;
        if (!is_array($codes)) {
            throw new AppError('Manda la lista de temporadas en "seasons" (puede ir vacía).', 400);
        }
        $codes = array_values(array_unique(array_map(function ($c) {
            if (!is_string($c)) {
                throw new AppError('Cada temporada debe ser un texto, por ejemplo "winter".', 400);
            }
            return strtolower(trim($c));
        }, $codes)));

        $themes = [];
        foreach (StoreService::rows($pdo, 'SELECT id_theme, code FROM dbo.season_theme') as $row) {
            $themes[$row['code']] = (int)$row['id_theme'];
        }
        foreach ($codes as $code) {
            if (!isset($themes[$code])) {
                throw new AppError('La temporada "' . $code . '" no existe.', 400);
            }
        }

        $pdo->beginTransaction();
        try {
            $off = $pdo->prepare('UPDATE dbo.product_season SET deleted = 1, updated_at = SYSUTCDATETIME()
                                  WHERE id_product = :id AND deleted = 0');
            $off->execute([':id' => $idProduct]);
            $on = $pdo->prepare("
                MERGE dbo.product_season AS t
                USING (SELECT :id_product AS id_product, :id_theme AS id_theme) AS s
                   ON t.id_product = s.id_product AND t.id_theme = s.id_theme
                WHEN MATCHED THEN UPDATE SET deleted = 0, updated_at = SYSUTCDATETIME(), source_node = 'web_node'
                WHEN NOT MATCHED THEN INSERT (id_product, id_theme, source_node) VALUES (s.id_product, s.id_theme, 'web_node');");
            foreach ($codes as $code) {
                $on->bindValue(':id_product', $idProduct, PDO::PARAM_INT);
                $on->bindValue(':id_theme', $themes[$code], PDO::PARAM_INT);
                $on->execute();
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return ['id_product' => $idProduct, 'seasons' => $codes];
    }
}
