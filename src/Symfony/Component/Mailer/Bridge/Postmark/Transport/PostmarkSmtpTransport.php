<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Kevin Verschaeve
 */
class PostmarkSmtpTransport extends EsmtpTransport
{
    private $messageStream;

    public function __construct(string $id, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('smtp.postmarkapp.com', 587, false, $dispatcher, $logger);

        $this->setUsername($id);
        $this->setPassword($id);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addPostmarkHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addPostmarkHeaders(Message $message): void
    {
        $message->getHeaders()->addTextHeader('X-PM-KeepID', 'true');

        $headers = $message->getHeaders();

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                if ($headers->has('X-PM-Tag')) {
                    throw new TransportException('Postmark only allows a single tag per email.');
                }

                $headers->addTextHeader('X-PM-Tag', $header->getValue());
                $headers->remove($name);
            }

            if ($header instanceof MetadataHeader) {
                $headers->addTextHeader('X-PM-Metadata-'.$header->getKey(), $header->getValue());
                $headers->remove($name);
            }
        }

        if (null !== $this->messageStream && !$message->getHeaders()->has('X-PM-Message-Stream')) {
            $headers->addTextHeader('X-PM-Message-Stream', $this->messageStream);
        }
    }

    /**
     * @return $this
     */
    public function setMessageStream(string $messageStream): static
    {
        $this->messageStream = $messageStream;

        return $this;
    }
}
