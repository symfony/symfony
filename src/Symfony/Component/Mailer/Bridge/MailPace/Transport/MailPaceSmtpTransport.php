<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailPace\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Paul Oms <support@mailpace.com>
 */
final class MailPaceSmtpTransport extends EsmtpTransport
{
    public function __construct(string $id, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('smtp.mailpace.com', 587, false, $dispatcher, $logger);

        $this->setUsername($id);
        $this->setPassword($id);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addMailPaceHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addMailPaceHeaders(Message $message): void
    {
        $headers = $message->getHeaders();

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                if (null != $headers->get('X-MailPace-Tags')) {
                    $existing = $headers->get('X-MailPace-Tags')->getBody();
                    $headers->remove('X-MailPace-Tags');
                    $headers->addTextHeader('X-MailPace-Tags', $existing.', '.$header->getValue());
                } else {
                    $headers->addTextHeader('X-MailPace-Tags', $header->getValue());
                }
                $headers->remove($name);
            }
        }
    }
}
