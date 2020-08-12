<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerRegistryInterface extends RegistryInterface
{
    public function get(string $name): SchedulerInterface;

    public function register(string $name, SchedulerInterface $scheduler): void;

    public function override(string $name, SchedulerInterface $scheduler): void;
}
