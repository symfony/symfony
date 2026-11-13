<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Relays through the SMTP server of Groundmail.
 *
 * The server listens with STARTTLS on port 587, and on port 2525 for hosts that block port 587.
 *
 * @see https://docs.groundmail.dev/api/smtp
 *
 * @author Shivansh Mehendiratta <shivansh@backslope.studio>
 */
final class GroundmailSmtpTransport extends EsmtpTransport
{
    public function __construct(string $username, #[\SensitiveParameter] string $password, ?int $port = null, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('smtp.groundmail.dev', $port ?? 587, false, $dispatcher, $logger);

        $this->setRequireTls(true);
        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addTagHeaders($message->getHeaders());
        }

        return parent::send($message, $envelope);
    }

    /**
     * The relay stores "X-Groundmail-Tag: name=value" headers as the tags of the email, as the API transport does with metadata.
     */
    private function addTagHeaders(Headers $headers): void
    {
        foreach ($headers->all() as $name => $header) {
            if ($header instanceof MetadataHeader) {
                $headers->remove($name);
                $headers->addTextHeader(GroundmailApiTransport::HEADER_TAG, $header->getKey().'='.$header->getValue());
            }
        }
    }
}
