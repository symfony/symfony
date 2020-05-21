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

namespace Symfony\Component\Scheduler\Bag;

use Symfony\Component\Scheduler\RegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BagRegistryInterface extends RegistryInterface
{
    public function get(string $name): BagInterface;

    public function register(TaskInterface $task, BagInterface $bag): void;
}
