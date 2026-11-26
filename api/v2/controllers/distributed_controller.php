<?php
/**
 * Base de datos distribuida: estado de los nodos, fragmentos reconstruidos, replicacion,
 * sincronizacion y copia al espejo en Azure SQL. Solo administradores.
 *
 * Si un nodo no responde, el servicio lee sus datos de otra copia (espejo en Azure o nodo
 * central) y lo indica en "source". Si la operacion completa no se puede hacer, responde 503
 * con la causa (por ejemplo, el nodo central caido).
 * Layer: controllers.
 */
final class DistributedController extends ApiController
{
    /** Ejecuta la accion del servicio; un fallo de nodos sale como 503 con su causa. */
    private function run(callable $action): array
    {
        store_require_admin($this->auth_user);
        try {
            return $action();
        } catch (AppError | InvalidArgumentException $error) {
            throw $error;
        } catch (Throwable $error) {
            log_exception('Endpoint distribuido: ' . $error->getMessage());
            throw new ServiceUnavailableError(dist_error_text($error));
        }
    }

    public function nodes(): array
    {
        return $this->run(static fn(): array => [
            'default_node' => JAM_DEFAULT_NODE,
            'nodes' => dist_nodes_status(),
        ]);
    }

    public function inventory(): array
    {
        $node = $this->query_text('node');
        return $this->run(static fn(): array => dist_inventory($node));
    }

    public function orders(): array
    {
        $node = $this->query_text('node');
        return $this->run(static fn(): array => dist_orders($node));
    }

    public function customers(): array
    {
        return $this->run(static fn(): array => dist_customers());
    }

    public function fragments(): array
    {
        return $this->run(static fn(): array => dist_fragments_summary());
    }

    public function replication(): array
    {
        return $this->run(static fn(): array => dist_replication_status());
    }

    public function sync(): array
    {
        return $this->run(static fn(): array => dist_sync());
    }

    public function mirror_sync(): array
    {
        $full = $this->query_text('full') === '1';
        return $this->run(static fn(): array => mirror_copy($full));
    }
}
