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
 * @author Rostislav Kaleta<rostislavkaleta@gmail.com>
 */
class IgbinarySerializer implements SerializerInterface
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

        $content = \is_resource($encodedEnvelope['body']) ? stream_get_contents($encodedEnvelope['body']) : $encodedEnvelope['body'];
        if (empty($content)) {
            throw new MessageDecodingFailedException('Failed to get encoded envelope content.');
        }

        return $this->safelyUnserialize($content);
    }

    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        return [
            'body' => igbinary_serialize($envelope),
        ];
    }

    private function safelyUnserialize(string $contents): Envelope
    {
        if ('' === $contents) {
            throw new MessageDecodingFailedException('Could not decode an empty message using \igbinary_deserialize.');
        }

        $signalingException = new MessageDecodingFailedException(sprintf('Could not decode message using \igbinary_deserialize: %s.', $contents));

        if ($this->acceptPhpIncompleteClass) {
            $prevUnserializeHandler = ini_set('unserialize_callback_func', null);
        } else {
            $prevUnserializeHandler = ini_set('unserialize_callback_func', self::class.'::handleUnserializeCallback');
        }
        $prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler, $signalingException) {
            if (__FILE__ === $file) {
                throw $signalingException;
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            /** @var Envelope */
            $envelope = igbinary_unserialize($contents);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }

        if (!$envelope instanceof Envelope) {
            throw $signalingException;
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
