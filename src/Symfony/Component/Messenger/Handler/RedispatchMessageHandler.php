<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class RedispatchMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(RedispatchMessage $message)
    {
        $this->bus->dispatch($message->envelope, [new TransportNamesStamp($message->transportNames)]);
    }
}
