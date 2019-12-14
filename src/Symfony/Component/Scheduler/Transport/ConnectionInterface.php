<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConnectionInterface
{
    public function list(): array;

    public function pause(string $taskName): void;

    public function resume(string $taskName): void;

    public function delete(string $taskName): void;

    public function empty(): void;
}
