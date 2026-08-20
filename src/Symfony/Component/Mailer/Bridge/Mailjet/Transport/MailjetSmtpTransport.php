<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

class MailjetSmtpTransport extends EsmtpTransport
{
    public function __construct(string $username, #[\SensitiveParameter] string $password, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('in-v3.mailjet.com', 587, false, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addMailjetHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addMailjetHeaders(Message $message): void
    {
        $headers = $message->getHeaders();

        if ($tracking = TrackingHeader::fromHeaders($headers)) {
            // an explicit X-Mailjet-Track* header wins over the generic one
            if (null !== $tracking->getOpens() && !$headers->has('X-Mailjet-TrackOpen')) {
                $headers->addTextHeader('X-Mailjet-TrackOpen', $tracking->getOpens() ? '1' : '0');
            }
            if (null !== $tracking->getClicks() && !$headers->has('X-Mailjet-TrackClick')) {
                $headers->addTextHeader('X-Mailjet-TrackClick', $tracking->getClicks() ? '1' : '0');
            }
            $headers->remove(TrackingHeader::NAME);
        }
    }
}
