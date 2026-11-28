<?php
declare(strict_types=1);

namespace Apicore\Controllers;

use Apicore\Core\AppError;
use Apicore\Core\Logger;
use Apicore\Core\ServiceUnavailableError;
use Apicore\Core\Settings;
use Apicore\Services\DistributedService;
use Apicore\Services\MirrorService;
use Apicore\Services\StoreService;
use Apicore\Services\TransactionService;
use Throwable;
use ValueError;

/**
 * Distributed database: node status, rebuilt fragments, replication, sync and
 * the copy to the Azure SQL mirror. Administrators only.
 *
 * When a node does not answer, the service reads its data from another copy
 * (the Azure mirror or the central node) and says so in "source". When the
 * whole operation cannot be done, it answers 503 with the cause (for example,
 * the central node down).
 *
 * Layer: controllers.
 */
final class DistributedController extends ActionController
{
    /**
     * Run the service action; a node failure becomes a 503 with its cause.
     *
     * @param callable(): array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function run(callable $action): array
    {
        StoreService::requireAdmin($this->authUser);
        try {
            return $action();
        } catch (AppError | ValueError $error) {
            throw $error;
        } catch (Throwable $error) {
            Logger::exception('Distributed endpoint: ' . $error->getMessage());
            throw new ServiceUnavailableError(DistributedService::errorText($error));
        }
    }

    /** @return array<string, mixed> */
    public function nodes(): array
    {
        return $this->run(static fn (): array => [
            'default_node' => Settings::instance()->JAM_DEFAULT_NODE,
            'nodes' => DistributedService::nodesStatus(),
        ]);
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        $node = $this->queryText('node');
        return $this->run(static fn (): array => DistributedService::inventory($node));
    }

    /** @return array<string, mixed> */
    public function orders(): array
    {
        $node = $this->queryText('node');
        return $this->run(static fn (): array => DistributedService::orders($node));
    }

    /** @return array<string, mixed> */
    public function customers(): array
    {
        return $this->run(static fn (): array => DistributedService::customers());
    }

    /** @return array<string, mixed> */
    public function fragments(): array
    {
        return $this->run(static fn (): array => DistributedService::fragmentsSummary());
    }

    /** @return array<string, mixed> */
    public function replication(): array
    {
        return $this->run(static fn (): array => DistributedService::replicationStatus());
    }

    /** @return array<string, mixed> */
    public function sync(): array
    {
        return $this->run(static fn (): array => DistributedService::sync());
    }

    /** @return array<string, mixed> */
    public function transfer(string $body): array
    {
        $data = $this->body($body);
        return $this->run(static fn (): array => TransactionService::transfer($data));
    }

    /** @return array<string, mixed> */
    public function transactions(): array
    {
        $limit = (int) ($this->queryText('limit') ?? 30);
        return $this->run(static fn (): array => TransactionService::overview($limit));
    }

    /** @return array<string, mixed> */
    public function checkpoint(): array
    {
        return $this->run(static fn (): array => TransactionService::checkpoint());
    }

    /** @return array<string, mixed> */
    public function mirrorSync(): array
    {
        $full = $this->queryText('full') === '1';
        return $this->run(static fn (): array => MirrorService::copy($full));
    }
}
