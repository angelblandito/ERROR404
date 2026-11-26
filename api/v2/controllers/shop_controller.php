<?php
/**
 * Tienda en linea: catalogo, resenas, comunicados, temporadas y codigos postales.
 * Layer: controllers. Lee parametros, llama a los servicios y devuelve datos.
 */
final class ShopController extends ApiController
{
    public function catalog(): array
    {
        return store_catalog();
    }

    public function reviews(int $id_product): array
    {
        return store_reviews($id_product);
    }

    public function also_bought(int $id_product): array
    {
        return store_also_bought($id_product);
    }

    public function create_review(string $body): array
    {
        return store_create_review($this->auth_user, $this->body($body));
    }

    public function announcements(): array
    {
        return account_announcements($this->auth_user);
    }

    public function postal_code(string $zip): array
    {
        if (!preg_match('/^\d{5}$/', $zip)) {
            throw new HttpError(404, 'Ruta no encontrada.');
        }
        return store_postal_code($zip);
    }

    public function current_theme(): array
    {
        return season_theme_current($this->query_text('date'));
    }
}
