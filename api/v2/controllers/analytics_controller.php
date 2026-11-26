<?php
/**
 * Visitas, busquedas y descargas de la app. El registro es publico (lo manda la pagina);
 * los reportes son solo para el administrador.
 * Layer: controllers.
 */
final class AnalyticsController extends ApiController
{
    public function ping(string $body): array
    {
        return analytics_ping(store_db(), $this->body($body), $this->auth_user);
    }

    public function leave(string $body): array
    {
        return analytics_leave(store_db(), $this->body($body));
    }

    public function search(string $body): array
    {
        return analytics_search(store_db(), $this->body($body));
    }

    public function install(string $body): array
    {
        return analytics_install(store_db(), $this->body($body));
    }

    public function report(): array
    {
        store_require_admin($this->auth_user);
        return analytics_report(store_db());
    }

    public function online(): array
    {
        store_require_admin($this->auth_user);
        return analytics_online(store_db());
    }
}
