<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Messenger\Middleware;

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Automatically adds a stamp to mail messages, so these are only dispatched once the main message handler(s) succeeded.
 * It prevents sending mails despite the main process failed.
 * MUST be registered before the dispatch_after_current_bus middleware so the stamp is taken into account.
 *
 * @see \Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class SendMailAfterCurrentBusMiddleware implements MiddlewareInterface
{
    /**
     * @var bool this property is used to signal if we are inside a the first/root call to
     *           MessageBusInterface::dispatch() or if dispatch has been called inside a message handler
     */
    private $isRootDispatchCallRunning = false;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            if ($this->isRootDispatchCallRunning && $envelope->getMessage() instanceof SendEmailMessage) {
                $envelope = $envelope->with(new DispatchAfterCurrentBusStamp());
            }

            // First time we get here, mark as inside a "root dispatch" call:
            $this->isRootDispatchCallRunning = true;

            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->isRootDispatchCallRunning = false;
        }
    }
}
