<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @experimental in 4.2
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    private $sendersLocator;

    public function __construct(SendersLocatorInterface $sendersLocator)
    {
        $this->sendersLocator = $sendersLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->all(ReceivedStamp::class)) {
            // it's a received message, do not send it back
            return $stack->next()->handle($envelope, $stack);
        }
        $handle = false;
        $sender = null;

        foreach ($this->sendersLocator->getSenders($envelope, $handle) as $alias => $sender) {
            $envelope = $sender->send($envelope)->with(new SentStamp(\get_class($sender), \is_string($alias) ? $alias : null));
        }

        if (null === $sender || $handle) {
            return $stack->next()->handle($envelope, $stack);
        }

        // message should only be sent and not be handled by the next middleware
        return $envelope;
    }
}
