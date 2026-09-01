<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Yann LUCAS
 */
final class MailerSendSmtpTransport extends EsmtpTransport
{
    public function __construct(string $username, #[\SensitiveParameter] string $password, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('smtp.mailersend.net', 587, false, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addMailerSendHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addMailerSendHeaders(Message $message): void
    {
        $headers = $message->getHeaders();
        $tags = [];

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                if (5 === \count($tags)) {
                    throw new TransportException(\sprintf('Too many "%s" instances present in the email headers. MailerSend does not accept more than 5 tags on an email.', TagHeader::class));
                }

                $tags[] = $header->getValue();
                $headers->remove($name);
            }
        }

        if ($tags) {
            $headers->addTextHeader('X-MailerSend-Tags', implode(',', $tags));
        }
    }
}
