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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Ryan Weaver<ryan@symfonycasts.com>
 */
class PhpSerializer implements SerializerInterface
{
    private bool $acceptPhpIncompleteClass = false;

    /**
     * @internal
     */
    public function acceptPhpIncompleteClass(): void
    {
        $this->acceptPhpIncompleteClass = true;
    }

    /**
     * @internal
     */
    public function rejectPhpIncompleteClass(): void
    {
        $this->acceptPhpIncompleteClass = false;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Encoded envelope should have at least a "body", or maybe you should implement your own serializer.');
        }

        if (!str_ends_with($encodedEnvelope['body'], '}')) {
            $encodedEnvelope['body'] = base64_decode($encodedEnvelope['body']);
        }

        if ('' === $serializeEnvelope = stripslashes($encodedEnvelope['body'])) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Encoded envelope should have at least a "body", or maybe you should implement your own serializer.');
        }

        try {
            $envelope = $this->safelyUnserialize($serializeEnvelope);

            if (!$envelope instanceof Envelope) {
                return MessageDecodingFailedException::wrap($encodedEnvelope, 'Could not decode message into an Envelope.');
            }

            if ($envelope->getMessage() instanceof \__PHP_Incomplete_Class) {
                $envelope = $envelope->with(new MessageDecodingFailedStamp());
            }
        } catch (\Throwable $e) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Could not decode Envelope: '.$e->getMessage(), $e->getCode(), $e);
        }

        return $envelope;
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

    private function safelyUnserialize(string $contents): mixed
    {
        if ($this->acceptPhpIncompleteClass) {
            $prevUnserializeHandler = ini_set('unserialize_callback_func', null);
        } else {
            $prevUnserializeHandler = ini_set('unserialize_callback_func', self::class.'::handleUnserializeCallback');
        }
        $prevErrorHandler = set_error_handler(static function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler) {
            if (__FILE__ === $file && !\in_array($type, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            return unserialize($contents);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new InvalidArgumentException(\sprintf('Message class "%s" not found during decoding.', $class));
    }
}
