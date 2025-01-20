<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Farhad Hedayatifard <farhad@ahasend.com>
 */
class AhaSendSmtpTransport extends EsmtpTransport
{
    public function __construct(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('send.ahasend.com', 587, false, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addAhaSendHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addAhaSendHeaders(Message $message): void
    {
        $headers = $message->getHeaders();

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
                $headers->remove($name);
            }
        }
        if (!empty($tags)) {
            $headers->addTextHeader('AhaSend-Tags', implode(',', $tags));
        }
    }
}
