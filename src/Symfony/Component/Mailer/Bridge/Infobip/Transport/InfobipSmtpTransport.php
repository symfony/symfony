<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Infobip\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

final class InfobipSmtpTransport extends EsmtpTransport
{
    public function __construct(#[\SensitiveParameter] string $key, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('smtp-api.infobip.com', 587, false, $dispatcher, $logger);

        $this->setUsername('App');
        $this->setPassword($key);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addInfobipHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addInfobipHeaders(Message $message): void
    {
        $headers = $message->getHeaders();

        if ($tracking = TrackingHeader::fromHeaders($headers)) {
            // an explicit X-Infobip-Track* header wins over the generic one
            if (null !== $tracking->getOpens() && !$headers->has('X-Infobip-TrackOpens')) {
                $headers->addTextHeader('X-Infobip-TrackOpens', $tracking->getOpens() ? 'true' : 'false');
            }
            if (null !== $tracking->getClicks() && !$headers->has('X-Infobip-TrackClicks')) {
                $headers->addTextHeader('X-Infobip-TrackClicks', $tracking->getClicks() ? 'true' : 'false');
            }
            $headers->remove(TrackingHeader::NAME);
        }
    }
}
