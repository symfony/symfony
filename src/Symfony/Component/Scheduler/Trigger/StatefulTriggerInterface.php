<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

/**
 * @deprecated use {@see Schedule::lockFactory()} and {@see MessageGeneratorWithInstanceLocking} instead
 */
interface StatefulTriggerInterface extends TriggerInterface
{
    public function continue(\DateTimeImmutable $startedAt): void;
}
