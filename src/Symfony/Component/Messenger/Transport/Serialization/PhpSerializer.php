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
    private static $classNotFoundDetected = false;
    private $createClassNotFound = false;

    public function enableClassNotFoundCreation(bool $enable = true): void
    {
        $this->createClassNotFound = $enable;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body".');
        }

        if (false === strpos($encodedEnvelope['body'], '}', -1)) {
            $encodedEnvelope['body'] = base64_decode($encodedEnvelope['body']);
        }

        $serializeEnvelope = stripslashes($encodedEnvelope['body']);

        return $this->safelyUnserialize($serializeEnvelope);
    }

    /**
     * {@inheritdoc}
     */
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
        $signalingException = new MessageDecodingFailedException(sprintf('Could not decode message using PHP serialization: %s.', $contents));

        if ($this->createClassNotFound) {
            self::$classNotFoundDetected = false;
            $prevUnserializeHandler = ini_set('unserialize_callback_func', self::class.'::handleUnserializeCallbackWithClassCreation');
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
            $envelope = unserialize($contents);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }

        if (self::$classNotFoundDetected) {
            $envelope = $envelope->with(new MessageDecodingFailedStamp());
        }

        return $envelope;
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback($class)
    {
        throw new MessageDecodingFailedException(sprintf('Message class "%s" not found during decoding.', $class));
    }

    /**
     * @internal
     */
    public function handleUnserializeCallbackWithClassCreation($class)
    {
        self::$classNotFoundDetected = true;

        $parts = explode('\\', $class);
        $class = array_pop($parts);
        $namespace = implode('\\', $parts);
        $code = <<<EOPHP
            class $class
            {
                private \$__WARNING__ = '⚠⚠ WARNING This class could not be unserialized. A mock has been created on the fly. ⚠⚠';
            }
        EOPHP;

        if ($namespace) {
            eval("namespace $namespace { $code };");
        } else {
            eval($code);
        }
    }
}
