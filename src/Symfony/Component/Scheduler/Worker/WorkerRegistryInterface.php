<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Symfony\Component\Scheduler\RegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerRegistryInterface extends RegistryInterface
{
    public function get(string $name): WorkerInterface;

    public function register(string $name, WorkerInterface $worker): void;

    public function override(string $name, WorkerInterface $worker): void;
}
