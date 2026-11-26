<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Services\AdminService;
use Apicore\Services\AuditService;
use Apicore\Services\CatalogAdminService;
use Apicore\Services\MarketService;
use Apicore\Services\StoreService;

/**
 * Store admin panel. Every action requires an Administrador account
 * (account_tier.can_manage_store = 1). Every change is written to the audit log
 * (dbo.audit_log) and the long lists accept ?page=N&size=M.
 *
 * Layer: controllers.
 */
final class AdminController extends ActionController
{
    /** Administrator of the session (403 when the account does not manage the store). */
    private function admin(): array
    {
        return StoreService::requireAdmin($this->authUser);
    }

    public function summary(): array
    {
        $this->admin();
        return AdminService::summary(StoreService::db());
    }

    public function customers(): array
    {
        $this->admin();
        return $this->pageRows(AdminService::customers(StoreService::db()));
    }

    public function setTier(int $idCustomer, string $body): array
    {
        $admin = $this->admin();
        $data = $this->body($body);
        $result = AdminService::setTier(StoreService::db(), $admin, $idCustomer, $data);
        AuditService::record(StoreService::db(), $admin, 'set_account_tier', 'customer', $idCustomer, ['tier' => $data['tier'] ?? null]);
        return $result;
    }

    public function evaluatePriority(): array
    {
        $admin = $this->admin();
        $result = AdminService::evaluatePriority(StoreService::db());
        AuditService::record(StoreService::db(), $admin, 'evaluate_priority', 'customer', 0, ['promoted' => count($result['promoted'] ?? [])]);
        return $result;
    }

    public function orders(): array
    {
        $this->admin();
        return $this->pageRows(AdminService::orders(StoreService::db(), $this->queryText('status')));
    }

    public function setOrderStatus(int $idOrder, string $body): array
    {
        $admin = $this->admin();
        $data = $this->body($body);
        $result = AdminService::setOrderStatus(StoreService::db(), $admin, $idOrder, $data);
        AuditService::record(StoreService::db(), $admin, 'set_order_status', 'customer_order', $idOrder, ['status' => $data['status'] ?? null]);
        return $result;
    }

    public function setPrice(int $idProduct, string $body): array
    {
        $admin = $this->admin();
        $result = AdminService::setPrice(StoreService::db(), $idProduct, $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'update_price', 'product', $idProduct, ['base_price' => $result['base_price'] ?? null]);
        return $result;
    }

    public function setStock(string $body): array
    {
        $admin = $this->admin();
        $result = AdminService::setStock(StoreService::db(), $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'update_inventory', 'product_variant', (int) ($result['id_variant'] ?? 0),
            ['id_store' => $result['id_store'] ?? null, 'stock' => $result['stock'] ?? null]);
        return $result;
    }

    public function tickets(): array
    {
        $this->admin();
        return $this->pageRows(AdminService::tickets(StoreService::db(), $this->queryText('status')));
    }

    public function answerTicket(int $idTicket, string $body): array
    {
        $admin = $this->admin();
        $result = AdminService::answerTicket(StoreService::db(), $admin, $idTicket, $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'answer_ticket', 'support_ticket', $idTicket);
        return $result;
    }

    public function announcements(): array
    {
        $this->admin();
        return $this->pageRows(AdminService::announcements(StoreService::db()));
    }

    public function createAnnouncement(string $body): array
    {
        $admin = $this->admin();
        $result = AdminService::createAnnouncement(StoreService::db(), $admin, $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'create_announcement', 'announcement', (int) ($result['id_announcement'] ?? 0));
        return $result;
    }

    public function deleteAnnouncement(int $id): array
    {
        $admin = $this->admin();
        $result = AdminService::deleteAnnouncement(StoreService::db(), $id);
        AuditService::record(StoreService::db(), $admin, 'delete_announcement', 'announcement', $id);
        return $result;
    }

    public function emails(): array
    {
        $this->admin();
        return $this->pageRows(AdminService::emails(StoreService::db()));
    }

    public function market(): array
    {
        $this->admin();
        $pdo = StoreService::db();
        return ['competitors' => MarketService::competitors($pdo), 'offers' => MarketService::offers($pdo)];
    }

    public function createMarketPrice(string $body): array
    {
        $admin = $this->admin();
        $result = MarketService::create(StoreService::db(), $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'create_market_price', 'market_price', (int) ($result['id_market_price'] ?? 0));
        return $result;
    }

    public function updateMarketPrice(int $id, string $body): array
    {
        $admin = $this->admin();
        $result = MarketService::update(StoreService::db(), $id, $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'update_market_price', 'market_price', $id);
        return $result;
    }

    public function deleteMarketPrice(int $id): array
    {
        $admin = $this->admin();
        $result = MarketService::delete(StoreService::db(), $id);
        AuditService::record(StoreService::db(), $admin, 'delete_market_price', 'market_price', $id);
        return $result;
    }

    public function savePhoto(int $idProduct, string $body): array
    {
        $admin = $this->admin();
        $result = CatalogAdminService::savePhoto(StoreService::db(), $idProduct, $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'upload_photo', 'product', $idProduct, ['file' => $result['file'] ?? null]);
        return $result;
    }

    public function deletePhoto(int $idProduct): array
    {
        $admin = $this->admin();
        $result = CatalogAdminService::deletePhoto(StoreService::db(), $idProduct, $this->queryText('color'));
        AuditService::record(StoreService::db(), $admin, 'delete_photo', 'product', $idProduct, ['file' => $result['removed'] ?? null]);
        return $result;
    }

    public function setSeasons(int $idProduct, string $body): array
    {
        $admin = $this->admin();
        $result = CatalogAdminService::setSeasons(StoreService::db(), $idProduct, $this->body($body));
        AuditService::record(StoreService::db(), $admin, 'set_seasons', 'product', $idProduct, ['seasons' => $result['seasons'] ?? []]);
        return $result;
    }

    public function audit(): array
    {
        $this->admin();
        return $this->pageRows(AuditService::list(StoreService::db(), $this->queryText('action'), $this->queryText('entity')));
    }
}
