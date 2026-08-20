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
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
trait MandrillHeadersTrait
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            $message = clone $message;
            $this->addMandrillHeaders($message);
        }

        return parent::send($message, $envelope);
    }

    private function addMandrillHeaders(Message $message): void
    {
        $headers = $message->getHeaders();
        $metadata = [];
        $tags = [];

        foreach ($headers->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
                $headers->remove($name);
            } elseif ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
                $headers->remove($name);
            }
        }

        if ($tracking = TrackingHeader::fromHeaders($headers)) {
            // an explicit X-MC-Track header wins over the generic one, and a header with
            // both flags null asks for the account defaults, which need no header at all
            if ((null !== $tracking->getOpens() || null !== $tracking->getClicks()) && !$headers->has('X-MC-Track')) {
                $enabledAspects = [];
                if (true === $tracking->getOpens()) {
                    $enabledAspects[] = 'opens';
                }
                if (true === $tracking->getClicks()) {
                    $enabledAspects[] = 'clicks';
                }

                // X-MC-Track only lists the aspects to enable; Mandrill disables tracking for any
                // other value, so "none" is used deliberately when no aspect is explicitly enabled.
                $headers->addTextHeader('X-MC-Track', $enabledAspects ? implode(',', $enabledAspects) : 'none');
            }
            $headers->remove(TrackingHeader::NAME);
        }

        if ($tags) {
            $headers->addTextHeader('X-MC-Tags', implode(',', $tags));
        }

        if ($metadata) {
            $headers->addTextHeader('X-MC-Metadata', json_encode($metadata));
        }
    }
}
