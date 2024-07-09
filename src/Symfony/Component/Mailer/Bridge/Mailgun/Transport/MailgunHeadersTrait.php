<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Transport;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait MailgunHeadersTrait
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addMailgunHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addMailgunHeaders(Message $message): void
    {
        $headers = $message->getHeaders();
        $metadata = [];

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $headers->addTextHeader('X-Mailgun-Tag', $header->getValue());
                $headers->remove($name);
            } elseif ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
                $headers->remove($name);
            }
        }

        if ($metadata) {
            $headers->addTextHeader('X-Mailgun-Variables', json_encode($metadata));
        }
    }
}
