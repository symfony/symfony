<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;

/**
 * Maps the MessageIdStamp, CorrelationStamp and CausationStamp of a message to the "message_id", "correlation_id" and "causation_id" headers, and back.
 */
final class IdentityStampHeaderMapper implements StampHeaderMapperInterface
{
    private const HEADERS = [
        'message_id' => MessageIdStamp::class,
        'correlation_id' => CorrelationStamp::class,
        'causation_id' => CausationStamp::class,
    ];

    public function toHeaders(Envelope $envelope): array
    {
        $headers = [];

        foreach (self::HEADERS as $name => $class) {
            if ($stamp = $envelope->last($class)) {
                $headers[$name] = $stamp->getId();
            }
        }

        return $headers;
    }

    public function toStamps(array $headers): array
    {
        $stamps = [];

        foreach (self::HEADERS as $name => $class) {
            $id = $headers[$name] ?? null;

            // AMQP header tables can hold integers
            if (\is_int($id)) {
                $id = (string) $id;
            }

            if (\is_string($id) && '' !== $id) {
                $stamps[] = new $class($id);
            }
        }

        return $stamps;
    }
}
