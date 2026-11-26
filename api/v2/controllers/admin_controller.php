<?php
/**
 * Panel de administracion de la tienda. Todas las acciones exigen una cuenta con
 * account_tier.can_manage_store = 1.
 * Layer: controllers.
 */
final class AdminController extends ApiController
{
    /** Administrador de la sesion (403 si la cuenta no administra la tienda). */
    private function admin(): array
    {
        return store_require_admin($this->auth_user);
    }

    public function summary(): array
    {
        $this->admin();
        return admin_summary(store_db());
    }

    public function customers(): array
    {
        $this->admin();
        return admin_customers(store_db());
    }

    public function set_tier(int $id_customer, string $body): array
    {
        return admin_set_tier(store_db(), $this->admin(), $id_customer, $this->body($body));
    }

    public function evaluate_priority(): array
    {
        $this->admin();
        return admin_evaluate_priority(store_db());
    }

    public function orders(): array
    {
        $this->admin();
        return admin_orders(store_db(), $this->query_text('status'));
    }

    public function set_order_status(int $id_order, string $body): array
    {
        return admin_set_order_status(store_db(), $this->admin(), $id_order, $this->body($body));
    }

    public function set_price(int $id_product, string $body): array
    {
        $this->admin();
        return admin_set_price(store_db(), $id_product, $this->body($body));
    }

    public function set_stock(string $body): array
    {
        $this->admin();
        return admin_set_stock(store_db(), $this->body($body));
    }

    public function tickets(): array
    {
        $this->admin();
        return admin_tickets(store_db(), $this->query_text('status'));
    }

    public function answer_ticket(int $id_ticket, string $body): array
    {
        return admin_answer_ticket(store_db(), $this->admin(), $id_ticket, $this->body($body));
    }

    public function announcements(): array
    {
        $this->admin();
        return admin_announcements(store_db());
    }

    public function create_announcement(string $body): array
    {
        return admin_create_announcement(store_db(), $this->admin(), $this->body($body));
    }

    public function delete_announcement(int $id): array
    {
        $this->admin();
        return admin_delete_announcement(store_db(), $id);
    }

    public function emails(): array
    {
        $this->admin();
        return admin_emails(store_db());
    }

    public function market(): array
    {
        $this->admin();
        $pdo = store_db();
        return ['competitors' => market_competitors($pdo), 'offers' => market_offers($pdo)];
    }

    public function create_market_price(string $body): array
    {
        $this->admin();
        return market_create(store_db(), $this->body($body));
    }

    public function update_market_price(int $id, string $body): array
    {
        $this->admin();
        return market_update(store_db(), $id, $this->body($body));
    }

    public function delete_market_price(int $id): array
    {
        $this->admin();
        return market_delete(store_db(), $id);
    }
}
