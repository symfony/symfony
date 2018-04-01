<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Asynchronous\Middleware;

use Symphony\Component\Messenger\Asynchronous\Routing\SenderLocatorInterface;
use Symphony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symphony\Component\Messenger\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    private $senderLocator;

    public function __construct(SenderLocatorInterface $senderLocator)
    {
        $this->senderLocator = $senderLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        if ($message instanceof ReceivedMessage) {
            return $next($message->getMessage());
        }

        if (!empty($senders = $this->senderLocator->getSendersForMessage($message))) {
            foreach ($senders as $sender) {
                if (null === $sender) {
                    continue;
                }

                $sender->send($message);
            }

            if (!in_array(null, $senders, true)) {
                return;
            }
        }

        return $next($message);
    }
}
