<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Transport;

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
 * Relays through the SMTP server of Sendora, which reads the tag and the metadata from the X-Tag and X-Metadata-* headers.
 *
 * @see https://sendora.se/docs/smtp
 */
final class SendoraSmtpTransport extends EsmtpTransport
{
    private ?string $stream = null;

    /**
     * Without a port, the transport uses port 465 with TLS from the start. Any other port uses STARTTLS.
     */
    public function __construct(#[\SensitiveParameter] string $key, ?string $host = null, ?int $port = null, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($host ?? 'smtp.sendora.se', $port ?? 465, null, $dispatcher, $logger);

        $this->setRequireTls(true);
        // Sendora takes the server key as both the username and the password
        $this->setUsername($key);
        $this->setPassword($key);
    }

    /**
     * @param string $stream The id of the stream the messages go on, instead of the server's default transactional stream
     *
     * @return $this
     */
    public function setStream(string $stream): static
    {
        $this->stream = $stream;

        return $this;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addSendoraHeaders($message->getHeaders());
        }

        return parent::send($message, $envelope);
    }

    protected function parseMessageId(string $mtaResult): string
    {
        // A test server, which delivers nothing, answers "accepted as" where a live server answers "queued as"
        return preg_match('/^250 2\.0\.0 OK: (?:queued|accepted) as (\S+)/m', $mtaResult, $matches) ? $matches[1] : '';
    }

    /**
     * @see https://sendora.se/docs/smtp#set-the-stream-tag-metadata-and-tracking
     */
    private function addSendoraHeaders(Headers $headers): void
    {
        if ($tracking = TrackingHeader::fromHeaders($headers)) {
            // an explicit X-Sendora-Track-* header wins over the generic one
            if (null !== $tracking->getOpens() && !$headers->has('X-Sendora-Track-Opens')) {
                $headers->addTextHeader('X-Sendora-Track-Opens', $tracking->getOpens() ? 'true' : 'false');
            }
            if (null !== $tracking->getClicks() && !$headers->has('X-Sendora-Track-Links')) {
                $headers->addTextHeader('X-Sendora-Track-Links', $tracking->getClicks() ? 'true' : 'false');
            }

            $headers->remove(TrackingHeader::NAME);
        }

        if (null !== $this->stream && !$headers->has('X-Sendora-Stream')) {
            $headers->addTextHeader('X-Sendora-Stream', $this->stream);
        }
    }
}
