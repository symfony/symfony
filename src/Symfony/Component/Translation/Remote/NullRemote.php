<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Remote;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\Translation\Event\MessageEvent;
use Symfony\Component\Translation\Message\MessageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NullRemote implements RemoteInterface
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = class_exists(Event::class) ? LegacyEventDispatcherProxy::decorate($dispatcher) : $dispatcher;
    }

    public function send(MessageInterface $message): void
    {
        if (null !== $this->dispatcher) {
            $this->dispatcher->dispatch(new MessageEvent($message));
        }
    }

    public function __toString(): string
    {
        return 'null';
    }
}
