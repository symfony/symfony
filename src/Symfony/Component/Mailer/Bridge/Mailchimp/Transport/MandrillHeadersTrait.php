<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Transport;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait MandrillHeadersTrait
{
    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $this->addMandrillHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addMandrillHeaders(Message $message): void
    {
        $headers = $message->getHeaders();
        $metadata = [];

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $headers->addTextHeader('X-MC-Tags', $header->getValue());
                $headers->remove($name);
            } elseif ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
                $headers->remove($name);
            }
        }

        if ($metadata) {
            $headers->addTextHeader('X-MC-Metadata', json_encode($metadata));
        }
    }
}
