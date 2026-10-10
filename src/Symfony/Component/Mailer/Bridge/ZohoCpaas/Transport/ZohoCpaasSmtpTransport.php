<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Relays through the SMTP server of Zoho CPaaS, formerly ZeptoMail.
 *
 * The server depends on the data center of the account. The SMTP tab of the Agent shows it.
 *
 * @see https://www.zoho.com/cpaas/help/smtp-home.html
 *
 * @author Dennis Petersmann <dennis@petersmann.dev>
 */
final class ZohoCpaasSmtpTransport extends EsmtpTransport
{
    /**
     * Without a port, implicit TLS uses port 465 and STARTTLS port 587, as documented by Zoho.
     *
     * A port of your own replaces that. When $tls is null, the port decides: 465 uses implicit TLS and every other port uses STARTTLS.
     */
    public function __construct(string $username, #[\SensitiveParameter] string $password, ?string $host = null, ?int $port = null, ?bool $tls = null, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($host ?? 'smtp.zeptomail.com', $port ?? (true === $tls ? 465 : 587), $tls, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addTrackingHeaders($message->getHeaders());
        }

        return parent::send($message, $envelope);
    }

    /**
     * @see https://www.zoho.com/cpaas/help/email-tracking.html
     */
    private function addTrackingHeaders(Headers $headers): void
    {
        if (!$tracking = TrackingHeader::fromHeaders($headers)) {
            return;
        }

        // an explicit X-TM-*-TRACK header wins over the generic one
        if (null !== $tracking->getOpens() && !$headers->has('X-TM-OPEN-TRACK')) {
            $headers->addTextHeader('X-TM-OPEN-TRACK', $tracking->getOpens() ? 'true' : 'false');
        }
        if (null !== $tracking->getClicks() && !$headers->has('X-TM-CLICK-TRACK')) {
            $headers->addTextHeader('X-TM-CLICK-TRACK', $tracking->getClicks() ? 'true' : 'false');
        }

        $headers->remove(TrackingHeader::NAME);
    }
}
