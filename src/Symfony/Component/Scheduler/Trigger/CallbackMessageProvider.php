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

use Symfony\Component\Scheduler\Generator\MessageContext;

final class CallbackMessageProvider implements MessageProviderInterface, \Stringable
{
    private \Closure $callback;

    /**
     * @param callable(MessageContext): iterable<object> $callback
     */
    public function __construct(callable $callback, private string $id = '', private string $description = '')
    {
        $this->callback = $callback(...);
    }

    public function getMessages(MessageContext $context): iterable
    {
        return ($this->callback)($context);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->description ?: $this->id;
    }
}
