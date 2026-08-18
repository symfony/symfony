<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Message;

/**
 * Signs messages using PGP/MIME.
 *
 * @author Florent Morselli
 *
 * @experimental
 */
final class PgpMimeSignedMessageListener implements EventSubscriberInterface
{
    public const PRIORITY = -128;

    /**
     * @param \Closure(Message): Message $signer
     */
    public function __construct(
        private readonly \Closure $signer,
    ) {
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Message) {
            return;
        }
        if (!$message->getHeaders()->has('X-Pgp-Sign')) {
            return;
        }
        $message->getHeaders()->remove('X-Pgp-Sign');

        $event->setMessage(($this->signer)($message));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', self::PRIORITY],
        ];
    }
}
