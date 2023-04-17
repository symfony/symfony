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
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Uid;

/**
 * @author Ryan Weaver<ryan@symfonycasts.com>
 */
class PhpSerializer implements SerializerInterface
{
    private const MESSAGE_REGEX = '/s:45:".Symfony\\\\Component\\\\Messenger\\\\Envelope.message";(?<message>O:\\d+:"[\\\\A-Za-z0-9_]+":\\d+:(?<recursive>\\{((?>[^{}]+)|(?&recursive))*\\}))/x';
    private const ALLOWED_CLASSES = [
        \DateTime::class,
        \DateTimeImmutable::class,
        \DateTimeZone::class,
        Uid\Ulid::class,
        Uid\UuidV1::class,
        Uid\UuidV3::class,
        Uid\UuidV4::class,
        Uid\UuidV5::class,
        Uid\UuidV6::class,
        Uid\UuidV7::class,
        Uid\UuidV8::class,
    ];

    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body", or maybe you should implement your own serializer.');
        }

        if (!str_ends_with($encodedEnvelope['body'], '}')) {
            $encodedEnvelope['body'] = base64_decode($encodedEnvelope['body']);
        }

        $serializeEnvelope = stripslashes($encodedEnvelope['body']);

        if ('' === $serializeEnvelope) {
            throw new MessageDecodingFailedException('Could not decode an empty message using PHP serialization.');
        }

        return $this->safelyUnserialize($serializeEnvelope);
    }

    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $body = addslashes(serialize($envelope));

        if (!preg_match('//u', $body)) {
            $body = base64_encode($body);
        }

        return [
            'body' => $body,
        ];
    }

    private function safelyUnserialize(string $contents): Envelope
    {
        $signal = new MessageDecodingFailedException(
            sprintf('Could not decode message using PHP serialization: %s.', $contents),
        );

        $prevErrorHandler = set_error_handler(
            function ($type, $msg, $file, ...$args) use (&$prevErrorHandler, $signal) {
                return match (true) {
                    __FILE__ === $file => throw $signal,
                    null !== $prevErrorHandler => $prevErrorHandler($type, $msg, $file, ...$args),
                    default => false,
                };
            },
        );
        $prevUnserializeHandler = ini_set(
            'unserialize_callback_func',
            self::class.'::handleUnserializeCallback',
        );

        try {
            $envelope = null;
            $e = null;

            try {
                try {
                    $envelope = unserialize($contents);
                } catch (\Throwable $e) {
                }

                if ($envelope instanceof Envelope) {
                    return $envelope;
                }

                if (null === $e || $e === $signal) {
                    throw new MessageDecodingFailedException($signal->getMessage());
                }

                if (!preg_match(self::MESSAGE_REGEX, $contents, $matches)) {
                    throw new MessageDecodingFailedException($signal->getMessage());
                }

                $encodedMessage = $matches['message'];
                $encodedEnvelope = str_replace($encodedMessage, serialize((object) []), $contents);

                // Unserialize envelope and stamps without unserializing actual message.
                try {
                    $envelope = unserialize($encodedEnvelope);
                } catch (\Throwable) {
                    throw new MessageDecodingFailedException($e->getMessage(), $e->getCode(), $e);
                }
            } finally {
                ini_set('unserialize_callback_func', $prevUnserializeHandler);
            }

            if (!($envelope instanceof Envelope)) {
                throw new MessageDecodingFailedException($signal->getMessage());
            }

            // Unserialize message, disallowing any class (uid and date time are allowed).
            try {
                $message = unserialize($encodedMessage, ['allowed_classes' => self::ALLOWED_CLASSES]);
            } catch (\Throwable) {
                throw new MessageDecodingFailedException($e->getMessage(), $e->getCode(), $e);
            }

            // Create a brand new envelope from this.
            return (new Envelope($message, array_merge(...array_values($envelope->all()))))
                ->with(new MessageDecodingFailedStamp($e->getMessage()));
        } finally {
            restore_error_handler();
        }
    }

    /** @internal */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new MessageDecodingFailedException(sprintf('Message class "%s" not found during decoding.', $class));
    }
}
