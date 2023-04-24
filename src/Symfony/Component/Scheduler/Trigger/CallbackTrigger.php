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
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental
 */
final class CallbackTrigger implements TriggerInterface
{
    private \Closure $callback;
    private string $description;

    public function __construct(callable $callback, string $description = null)
    {
        $this->callback = $callback(...);
        $this->description = $description ?? spl_object_hash($this->callback);
    }

    public function __toString(): string
    {
        return $this->description;
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        return ($this->callback)($run);
    }
}
