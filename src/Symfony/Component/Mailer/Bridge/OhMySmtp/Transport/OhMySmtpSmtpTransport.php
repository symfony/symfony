<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\OhMySmtp\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceSmtpTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

trigger_deprecation('symfony/oh-my-smtp-mailer', '6.2', 'The "%s" class is deprecated, use "%s" instead.', OhMySmtpSmtpTransport::class, MailPaceSmtpTransport::class);

/**
 * @author Paul Oms <support@ohmysmtp.com>
 *
 * @deprecated since Symfony 6.2, use MailPaceSmtpTransport instead
 */
final class OhMySmtpSmtpTransport extends EsmtpTransport
{
    public function __construct(string $id, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('smtp.ohmysmtp.com', 587, false, $dispatcher, $logger);

        $this->setUsername($id);
        $this->setPassword($id);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addOhMySmtpHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addOhMySmtpHeaders(Message $message): void
    {
        $headers = $message->getHeaders();

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                if (null != $headers->get('X-OMS-Tags')) {
                    $existing = $headers->get('X-OMS-Tags')->getBody();
                    $headers->remove('X-OMS-Tags');
                    $headers->addTextHeader('X-OMS-Tags', $existing.', '.$header->getValue());
                } else {
                    $headers->addTextHeader('X-OMS-Tags', $header->getValue());
                }
                $headers->remove($name);
            }
        }
    }
}
