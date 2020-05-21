<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Closure;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface RegistryInterface extends \Countable
{
    public function filter(Closure $filter): array;

    public function has(string $name): bool;

    public function remove(string $name): void;

    public function toArray(): array;
}
