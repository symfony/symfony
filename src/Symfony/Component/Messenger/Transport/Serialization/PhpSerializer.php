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
use Symfony\Component\Messenger\Stamp\HandledStamp;
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
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body", or maybe you should implement your own serializer.');
        }

        // Decoded strictly, as getMessageType() does, so that both read the same bytes: a lax
        // decode silently drops characters the alphabet does not allow, leaving the type the
        // signature is checked against to be read from a different payload than the one decoded.
        if (!str_ends_with($encodedEnvelope['body'], '}')) {
            if (false === $body = base64_decode($encodedEnvelope['body'], true)) {
                throw new MessageDecodingFailedException('Could not decode the envelope body: it is neither a serialized payload nor valid base64.');
            }

            $encodedEnvelope['body'] = $body;
        }

        $serializeEnvelope = stripslashes($encodedEnvelope['body']);

        return $this->safelyUnserialize($serializeEnvelope);
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
        // unserialize() with allowed_classes restricted to Envelope turns the carried
        // message into a __PHP_Incomplete_Class with no constructor / __wakeup /
        // __unserialize executed. This skips the in-PHP tokenizer below.
        // allowed_classes does not cover enums: unserialize() autoloads the class of an
        // enum case whatever the list. So once Envelope is loaded, autoloading is refused
        // while unserialize() runs, and the enum cases are replaced below when it was needed.
        // The capital-S string format, which serialize() never writes, can spell any property name with escapes: the tokenizer below refuses it.
        if (!str_contains($body, 'C:') && !str_contains($body, 'S:') && false !== $type = self::unserializeMessageType($body)) {
            return $type;
        }

        // The message can be a reference to a value written before it, e.g. by a stamp that
        // holds it. Native unserialize() resolves it the way decode() does, which the tokenizer
        // below cannot: the values that a Serializable payload unserializes are numbered too.
        // So unless the body really carries such a payload ("C:" can be part of a string), its
        // enum cases are replaced by incomplete objects of their class, which take the same
        // places, and unserialize() reads it without looking up any class.
        if (null !== $bodyWithoutEnumCases = self::replaceEnumCases($body)) {
            return self::unserializeMessageType($bodyWithoutEnumCases) ?: null;
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
        // The declared length is read as an integer, the way unserialize() reads it, rather than
        // compared as bytes: a length written with leading zeros denotes the very same property.
        $messageKeys = [
            "\0".Envelope::class."\0message",
            "\0*\0message",
            'message',
        ];
        $messageType = null;

        for ($i = 0; $i < $count; ++$i) {
            $isMessageKey = false;

            if (preg_match('/\Gs:(\d++):"/A', $body, $m, 0, $offset)) {
                $nameLength = (int) $m[1];
                $nameOffset = $offset + \strlen($m[0]);

                if ('";' === substr($body, $nameOffset + $nameLength, 2)) {
                    $isMessageKey = \in_array(substr($body, $nameOffset, $nameLength), $messageKeys, true);
                }
            }

            if (!self::skipSerializedValue($body, $offset)) {
                return null;
            }

            if ($isMessageKey) {
                // A reference (r: or R:) is not followed: the values that a Serializable payload unserializes are numbered too.
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

        // handler results are for synchronous callers: retries only need the names of the handlers that already ran
        if ($handledStamps = $envelope->all(HandledStamp::class)) {
            $envelope = $envelope->withoutAll(HandledStamp::class)->with(...array_map(static fn (HandledStamp $stamp) => new HandledStamp(null, $stamp->getHandlerName()), $handledStamps));
        }

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
        $prevErrorHandler = set_error_handler(static function ($type, $msg, $file, $line, $context = []) use (&$prevErrorHandler) {
            if (__FILE__ === $file && !\in_array($type, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            /** @var Envelope */
            $envelope = unserialize($contents, ['allowed_classes' => true]);
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

        // encoding strips these stamps, so they never come from a transport
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

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
        throw new MessageDecodingFailedException(\sprintf('Message class "%s" not found during decoding.', $class));
    }

    /**
     * Reads the type of the message with native unserialize(), without autoloading any class.
     *
     * @return string|false|null false when unserialize() needed to autoload a class
     */
    private static function unserializeMessageType(string $body): string|false|null
    {
        class_exists(Envelope::class);
        $autoloadAttempted = false;
        $autoloadGuard = static function () use (&$autoloadAttempted): never {
            $autoloadAttempted = true;

            throw new \LogicException('Autoloading is not allowed while reading the message type.');
        };
        spl_autoload_register($autoloadGuard, true, true);

        // An incomplete object stores the name of its class in a property that the payload can set too.
        // Renaming that property in the payload, with the same length, keeps the name written in the object header.
        $body = str_replace('__PHP_Incomplete_Class_Name', '__PHP_Incomplete_Class_Nam_', $body);

        try {
            $envelope = @unserialize($body, ['allowed_classes' => [Envelope::class]]);
            $message = $envelope instanceof Envelope ? $envelope->getMessage() : null;
        } catch (\Throwable) {
            $message = null;
        } finally {
            spl_autoload_unregister($autoloadGuard);
        }

        return match (true) {
            $autoloadAttempted => false,
            null === $message => null,
            $message instanceof \__PHP_Incomplete_Class => ((array) $message)['__PHP_Incomplete_Class_Name'],
            default => $message::class,
        };
    }

    /**
     * Replaces the enum cases of a serialized value by incomplete objects of their class.
     *
     * Each one stays a single value, so the references of the body keep pointing to the same values.
     * Returns null when the value carries a Serializable payload or is not a single serialized value.
     */
    private static function replaceEnumCases(string $body): ?string
    {
        $offset = 0;
        $enumCasesAndSerializables = [];

        if (!self::skipSerializedValue($body, $offset, 4096, $enumCasesAndSerializables) || isset($body[$offset])) {
            return null;
        }

        $replaced = '';
        $offset = 0;

        foreach ($enumCasesAndSerializables as $start => $end) {
            if ('C' === $body[$start]) {
                return null;
            }

            // E:NN:"<FQCN>:<case>"; becomes O:NN:"<FQCN>":0:{}
            $nameOffset = strpos($body, '"', $start) + 1;
            $name = substr($body, $nameOffset, $end - 2 - $nameOffset);

            if (!$colon = strpos($name, ':')) {
                return null;
            }

            $replaced .= substr($body, $offset, $start - $offset).'O:'.$colon.':"'.substr($name, 0, $colon).'":0:{}';
            $offset = $end;
        }

        return $replaced.substr($body, $offset);
    }

    /**
     * Advances $offset past one PHP-serialized value, without instantiating any object.
     *
     * Iterative (manual stack) to avoid PHP function-call overhead on deep payloads.
     * $maxDepth mirrors PHP's `unserialize_max_depth` so an adversarial deeply-nested
     * payload can't burn CPU or blow the stack here when native unserialize() would
     * have rejected it.
     *
     * @param array<int, int>|null $enumCasesAndSerializables Receives the start and end offsets of the enum cases (E:) and Serializable payloads (C:)
     */
    private static function skipSerializedValue(string $body, int &$offset, int $maxDepth = 4096, ?array &$enumCasesAndSerializables = null): bool
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
                    $start = $offset;
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
                        if ('E' === $kind) {
                            $enumCasesAndSerializables[$start] = $offset;
                        }
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
                        $enumCasesAndSerializables[$start] = $offset;
                    }
                    break;

                default:
                    return false;
            }
        }
    }
}
