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
use Symfony\Component\MimePgp\PgpSigner;

/**
 * Signs messages using PGP/MIME.
 *
 * @author Florent Morselli
 */
final class PgpMimeSignedMessageListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly PgpSigner $signer,
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

        $event->setMessage($this->signer->sign($message));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', -128],
        ];
    }
}
