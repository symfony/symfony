<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Messenger;

use Psr\Container\ContainerInterface;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
class ConsumeRemoteEventHandler
{
    public function __construct(
        private readonly ContainerInterface $consumers,
    ) {
    }

    public function __invoke(ConsumeRemoteEventMessage $message): void
    {
        if (!$this->consumers->has($message->getType())) {
            throw new LogicException(sprintf('Unable to find a consumer for message of type "%s".', $message->getType()));
        }
        $consumer = $this->consumers->get($message->getType());

        if (!$consumer instanceof ConsumerInterface) {
            throw new LogicException(sprintf('The consumer "%s" for message of type "%s" must implement "%s".', get_debug_type($consumer), $message->getType(), ConsumerInterface::class));
        }

        $consumer->consume($message->getEvent());
    }
}
