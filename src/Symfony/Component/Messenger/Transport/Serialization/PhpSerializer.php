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
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body", or maybe you should implement your own serializer.');
        }

        if (!str_ends_with($encodedEnvelope['body'], '}')) {
            $encodedEnvelope['body'] = base64_decode($encodedEnvelope['body']);
        }

        $serializeEnvelope = stripslashes($encodedEnvelope['body']);

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
        if ('' === $contents) {
            throw new MessageDecodingFailedException('Could not decode an empty message using PHP serialization.');
        }

        if ($this->acceptPhpIncompleteClass) {
            $prevUnserializeHandler = ini_set('unserialize_callback_func', null);
        } else {
            $prevUnserializeHandler = ini_set('unserialize_callback_func', self::class.'::handleUnserializeCallback');
        }
        $prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler) {
            if (__FILE__ === $file && !\in_array($type, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            /** @var Envelope */
            $envelope = unserialize($contents);
        } catch (\Throwable $e) {
            if ($e instanceof MessageDecodingFailedException) {
                throw $e;
            }

            throw new MessageDecodingFailedException('Could not decode Envelope: '.$e->getMessage(), 0, $e);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }

        if (!$envelope instanceof Envelope) {
            throw new MessageDecodingFailedException('Could not decode message into an Envelope.');
        }

        if ($envelope->getMessage() instanceof \__PHP_Incomplete_Class) {
            $envelope = $envelope->with(new MessageDecodingFailedStamp());
        }

        return $envelope;
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new MessageDecodingFailedException(sprintf('Message class "%s" not found during decoding.', $class));
    }
}
