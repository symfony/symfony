<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Transport;

use Symfony\Component\Scheduler\Transport\ConnectionInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connnection implements ConnectionInterface
{
    public function list(): array
    {
        // TODO: Implement list() method.
    }

    public function pause(string $taskName): void
    {
        // TODO: Implement pause() method.
    }

    public function resume(string $taskName): void
    {
        // TODO: Implement resume() method.
    }

    public function delete(string $taskName): void
    {
        // TODO: Implement delete() method.
    }

    public function empty(): void
    {
        // TODO: Implement empty() method.
    }
}
