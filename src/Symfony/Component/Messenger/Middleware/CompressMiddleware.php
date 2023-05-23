<?php

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;

class CompressMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($amqpReceivedStamp = $envelope->last(AmqpReceivedStamp::class)) {
            $contentEncoding ??= $amqpReceivedStamp->getAmqpEnvelope()->getContentEncoding();
            if (!$contentEncoding) {
                return $stack->next()->handle($envelope, $stack);
            }

            $message = $envelope->getMessage();
            $compress = $this->decompress($contentEncoding, $message);
            $envelope = new Envelope(
                $compress,
                $envelope->all()
            );
        } else {
            $amqpStamp = $envelope->last(AmqpStamp::class);
            $contentEncoding = $amqpStamp->getAttributes()['content_encoding'] ?? null;
            if (!$contentEncoding) {
                return $stack->next()->handle($envelope, $stack);
            }
            $message = $envelope->getMessage();
            // We need a string, but in this point we have an object
            $compress = $this->compress($contentEncoding, $message);
            $envelope = new Envelope(
                $compress,
                $envelope->all()
            );
        }

        return $stack->next()->handle($envelope, $stack);
    }

    public function compress(string $contentEncoding, mixed $data): string
    {
        return match ($contentEncoding) {
            'gzip' => gzencode($data),
            'deflate' => gzdeflate($data),
            default => throw new InvalidArgumentException(sprintf('The MIME content encoding of the message cannot be decompressed "%s".', $contentEncoding)),
        };
    }

    public function decompress(string $contentEncoding, mixed $data): mixed
    {
        return match ($contentEncoding) {
            'gzip' => gzdecode($data) ?: $data,
            'deflate' => gzinflate($data) ?: $data,
        };

        return $data;
    }
}
