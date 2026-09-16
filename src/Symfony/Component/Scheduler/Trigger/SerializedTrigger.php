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

use Symfony\Component\Scheduler\Exception\LogicException;

/**
 * What is left of a trigger once its message has crossed a transport.
 */
final class SerializedTrigger implements TriggerInterface
{
    public function __construct(
        private readonly string $description,
    ) {
    }

    public function __toString(): string
    {
        return $this->description;
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        throw new LogicException('Not possible to get next run date from a deserialized trigger.');
    }
}
