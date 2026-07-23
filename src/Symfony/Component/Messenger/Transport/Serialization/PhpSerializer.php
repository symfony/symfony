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
class PhpSerializer implements SerializerInterface, MessageTypeAwareSerializerInterface
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

    public function getMessageType(array $encodedEnvelope): ?string
    {
        if (!\is_string($body = $encodedEnvelope['body'] ?? null) || '' === $body) {
            return null;
        }

        if (!str_ends_with($body, '}') && false === $body = base64_decode($body, true)) {
            return null;
        }

        $body = stripslashes($body);

        // Fast path: when the body carries no Serializable (`C:`) payload, native
        // unserialize() with allowed_classes restricted to Envelope is safe and fast.
        // The carried message becomes a __PHP_Incomplete_Class with no constructor /
        // __wakeup / __unserialize executed. This skips the in-PHP tokenizer below.
        // The "allowed_classes" allowlist is the SOLE line of defense here: this call
        // intentionally runs without installing the unserialize_callback_func / error
        // handler guards used by safelyUnserialize(). Any future change that widens
        // "allowed_classes" beyond Envelope MUST re-introduce those guards.
        if (!str_contains($body, 'C:')) {
            try {
                $envelope = @unserialize($body, ['allowed_classes' => [Envelope::class]]);

                if (!$envelope instanceof Envelope) {
                    return null;
                }
                $message = $envelope->getMessage();
            } catch (\Throwable) {
                return null;
            }

            return $message instanceof \__PHP_Incomplete_Class ? ((array) $message)['__PHP_Incomplete_Class_Name'] : $message::class;
        }

        // Slow path: parse the serialized envelope without invoking unserialize() so that
        // no Serializable payload is mis-decoded as a __PHP_Incomplete_Class (which has no
        // unserializer for the `C:` format and would trigger a PHP warning, sometimes
        // returning false).
        if (!preg_match('/^O:36:"Symfony\\\\Component\\\\Messenger\\\\Envelope":(\d+):\{/', $body, $m)) {
            return null;
        }
        $offset = \strlen($m[0]);
        $count = (int) $m[1];

        // PHP unserialize() accepts all three property-name encodings and routes any of
        // them to Envelope::$message, so the scanner must recognize all three forms too,
        // otherwise an attacker can hide the real message behind a non-canonical key.
        $messageKeys = [
            's:45:"'."\0".Envelope::class."\0".'message";',
            's:10:"'."\0*\0".'message";',
            's:7:"message";',
        ];
        $messageType = null;

        for ($i = 0; $i < $count; ++$i) {
            $isMessageKey = false;
            foreach ($messageKeys as $key) {
                if (0 === substr_compare($body, $key, $offset, \strlen($key))) {
                    $isMessageKey = true;
                    break;
                }
            }

            if (!self::skipSerializedValue($body, $offset)) {
                return null;
            }

            if ($isMessageKey) {
                if (!preg_match('/\G[OCE]:(\d+):"/A', $body, $m, 0, $offset)) {
                    return null;
                }
                $kind = $body[$offset];
                $nameLen = (int) $m[1];
                $nameOffset = $offset + \strlen($m[0]);

                if ($nameLen <= 0 || \strlen($body) < $nameOffset + $nameLen + 1 || '"' !== $body[$nameOffset + $nameLen]) {
                    return null;
                }

                $name = substr($body, $nameOffset, $nameLen);

                if ('E' === $kind) {
                    // Enum: name has the form "FQCN:CaseName"; only the class part matters here.
                    if (!$colon = strpos($name, ':')) {
                        return null;
                    }
                    $name = substr($name, 0, $colon);
                }

                // PHP unserialize() uses last-wins for duplicate keys; mirror that semantics.
                $messageType = $name;
            }

            if (!self::skipSerializedValue($body, $offset)) {
                return null;
            }
        }

        if (($body[$offset] ?? '') !== '}' || isset($body[$offset + 1])) {
            return null;
        }

        return $messageType;
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
            return unserialize($contents, ['allowed_classes' => true]);
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

    /**
     * Advances $offset past one PHP-serialized value, without instantiating any object.
     *
     * Iterative (manual stack) to avoid PHP function-call overhead on deep payloads.
     * $maxDepth mirrors PHP's `unserialize_max_depth` so an adversarial deeply-nested
     * payload can't burn CPU or blow the stack here when native unserialize() would
     * have rejected it.
     */
    private static function skipSerializedValue(string $body, int &$offset, int $maxDepth = 4096): bool
    {
        // $remaining[$d] = number of values still to parse at nesting level $d
        // (one $count pair contributes 2 values: a key and a value).
        $depth = 0;
        $remaining = [0 => 1]; // one value to read at the top level

        while (true) {
            // Close completed nesting levels (`}` per level).
            while (0 === $remaining[$depth]) {
                if (0 === $depth) {
                    return true;
                }
                if (($body[$offset] ?? '') !== '}') {
                    return false;
                }
                ++$offset;
                unset($remaining[$depth--]);
            }

            --$remaining[$depth];

            if (!isset($body[$offset])) {
                return false;
            }
            $sep = null;

            switch ($kind = $body[$offset]) {
                case 'N': // N;
                case 'b': // b:0; or b:1;
                case 'i': // i:NN;
                case 'd': // d:NN[.NN][E±NN]; or d:INF; / d:NAN;
                case 'r': // r:NN;
                case 'R': // R:NN;
                    $end = $offset + strspn($body, '0123456789+-.:;NbidrRIFAE', $offset);
                    while (';' !== $body[$end - 1]) {
                        if (--$end === $offset) {
                            return false;
                        }
                    }
                    $remaining[$depth] -= substr_count($body, ';', $offset, $end - $offset) - 1;
                    $offset = $end;
                    break;

                case 'a': // a:NN:{<key>;<value>; ...}
                    if (':' !== ($body[$offset + 1] ?? '')) {
                        return false;
                    }
                    $d = strspn($body, '0123456789', $offset + 2);
                    if (0 === $d || '{' !== ($body[$offset + 3 + $d] ?? '') || ':' !== $body[$offset + 2 + $d]) {
                        return false;
                    }
                    if (++$depth > $maxDepth) {
                        return false;
                    }
                    $remaining[$depth] = (int) substr($body, $offset + 2, $d) << 1;
                    $offset += 4 + $d;
                    break;

                case 's': // s:NN:"...";
                case 'E': // E:NN:"<FQCN>:<case>";
                    $sep = ';';
                    // no break;
                case 'O': // O:NN:"FQCN":NN:{<key>;<value>; ...}
                case 'C': // C:NN:"FQCN":NN:{<opaque payload>}
                    $sep ??= ':';
                    if (':' !== ($body[$offset + 1] ?? '')) {
                        return false;
                    }
                    $d = strspn($body, '0123456789', $offset + 2);
                    if (0 === $d || '"' !== ($body[$offset + 3 + $d] ?? '') || ':' !== $body[$offset + 2 + $d]) {
                        return false;
                    }
                    $offset += 4 + $d + (int) substr($body, $offset + 2, $d);
                    if (($body[$offset + 1] ?? '') !== $sep || '"' !== $body[$offset]) {
                        return false;
                    }
                    $offset += 2;
                    if (';' === $sep) {
                        break;
                    }

                    $d = strspn($body, '0123456789', $offset);
                    if (0 === $d || '{' !== ($body[$offset + 1 + $d] ?? '') || ':' !== $body[$offset + $d]) {
                        return false;
                    }
                    $count = (int) substr($body, $offset, $d);
                    $offset += 2 + $d;
                    if ('O' === $kind) {
                        if (++$depth > $maxDepth) {
                            return false;
                        }
                        $remaining[$depth] = $count << 1;
                    } else {
                        // C: opaque payload of $count bytes followed by `}`.
                        $offset += $count;
                        if (($body[$offset] ?? '') !== '}') {
                            return false;
                        }
                        ++$offset;
                    }
                    break;

                default:
                    return false;
            }
        }
    }
}
