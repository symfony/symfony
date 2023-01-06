<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Kevin Verschaeve
 */
class SesSmtpTransport extends EsmtpTransport
{
    /**
     * @param string|null $region Amazon SES region
     */
    public function __construct(string $username, #[\SensitiveParameter] string $password, string $region = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null, string $host = 'default')
    {
        if ('default' === $host) {
            $host = sprintf('email-smtp.%s.amazonaws.com', $region ?: 'eu-west-1');
        }

        parent::__construct($host, 465, true, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addSesHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addSesHeaders(Message $message): void
    {
        $metadata = [];
        $headers = $message->getHeaders();

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[] = "{$header->getKey()}={$header->getValue()}";
                $headers->remove($name);
            }
        }

        if ($metadata) {
            $headers->addTextHeader('X-SES-MESSAGE-TAGS', implode(', ', $metadata));
        }
    }
}
