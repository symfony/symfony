<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * Serializes values using phpser when available, or native PHP serialization otherwise.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DefaultMarshaller implements MarshallerInterface
{
    private bool $useIgbinarySerialize = false;
    private bool $usePhpSerSerialize = false;
    private bool $throwOnSerializationFailure = false;
    private \Closure|string|null $signingKey;

    /**
     * @param bool|null                       $useIgbinarySerialize Passing true is deprecated since Symfony 8.2; pass false to force native PHP serialization
     * @param string|(\Closure():string)|null $signingKey           A key of at least 32 bytes, or a closure that returns it, to sign and verify values when phpser is loaded
     */
    public function __construct(
        ?bool $useIgbinarySerialize = null,
        bool $throwOnSerializationFailure = false,
        #[\SensitiveParameter] \Closure|string|null $signingKey = null,
    ) {
        if (null !== $signingKey) {
            if (\is_string($signingKey)) {
                self::checkSigningKey($signingKey);
            }
            if (true === $useIgbinarySerialize) {
                throw new InvalidArgumentException('A phpser signing key cannot be used with igbinary serialization.');
            }
            if (false === $useIgbinarySerialize) {
                throw new InvalidArgumentException('A phpser signing key cannot be used with native PHP serialization.');
            }
        }
        if ($useIgbinarySerialize) {
            trigger_deprecation('symfony/cache', '8.2', 'Using igbinary in "%s" is deprecated, use the "phpser" extension instead.', self::class);
        }
        if ($useIgbinarySerialize && (!\extension_loaded('igbinary') || version_compare('3.1.6', phpversion('igbinary'), '>'))) {
            throw new CacheException(\extension_loaded('igbinary') ? 'Please upgrade the "igbinary" PHP extension to v3.1.6 or higher.' : 'The "igbinary" PHP extension is not loaded.');
        }
        $this->useIgbinarySerialize = true === $useIgbinarySerialize;
        // checked here and not when compiling the container, which can happen where phpser is not loaded
        $this->usePhpSerSerialize = null === $useIgbinarySerialize && self::isPhpSerLoaded();
        $this->signingKey = $this->usePhpSerSerialize ? $signingKey : null;
        $this->throwOnSerializationFailure = $throwOnSerializationFailure;
    }

    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = $failed = [];
        $signingKey = $this->getSigningKey();

        foreach ($values as $id => $value) {
            try {
                if ($this->useIgbinarySerialize) {
                    $serialized[$id] = igbinary_serialize($value);
                } elseif (null !== $signingKey) {
                    $serialized[$id] = phpser_serialize_signed($value, $signingKey);
                } elseif ($this->usePhpSerSerialize) {
                    $serialized[$id] = phpser_serialize($value);
                } else {
                    $serialized[$id] = serialize($value);
                }
            } catch (\Exception $e) {
                if ($this->throwOnSerializationFailure) {
                    throw new \ValueError($e->getMessage(), 0, $e);
                }
                $failed[] = $id;
            }
        }

        return $serialized;
    }

    public function unmarshall(string $value): mixed
    {
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        try {
            if (null !== $signingKey = $this->getSigningKey()) {
                try {
                    return phpser_unserialize_signed($value, $signingKey);
                } catch (\Exception $e) {
                    throw new \DomainException('Failed to verify or unserialize value: '.$e->getMessage(), 0, $e);
                }
            }
            if ('b:0;' === $value) {
                return false;
            }
            if ('N;' === $value) {
                return null;
            }
            // phpser values start with a version byte, native ones with a letter and igbinary ones with \x00
            if ('' !== $value && "\x00" < $value[0] && "\x20" > $value[0]) {
                if (!self::isPhpSerLoaded()) {
                    throw new \RuntimeException(\extension_loaded('phpser') ? 'Please upgrade the "phpser" PHP extension to v0.10.3 or higher.' : 'Failed to unserialize values, did you forget to install the "phpser" extension?');
                }
                static $phpserNull;
                if ($value === ($phpserNull ??= phpser_serialize(null))) {
                    return null;
                }
                if (null !== $value = phpser_unserialize($value)) {
                    return $value;
                }

                throw new \DomainException('Failed to unserialize phpser value.');
            } elseif (':' === ($value[1] ?? ':')) {
                if (false !== $value = unserialize($value, ['allowed_classes' => true])) {
                    return $value;
                }
            } else {
                trigger_deprecation('symfony/cache', '8.2', 'Unmarshalling igbinary values in "%s" is deprecated, use the "phpser" extension instead.', self::class);
                if (!\extension_loaded('igbinary')) {
                    throw new \RuntimeException('Failed to unserialize values, did you forget to install the "igbinary" extension?');
                }
                if ($value === igbinary_serialize(null)) {
                    return null;
                }
                if (null !== $value = igbinary_unserialize($value)) {
                    return $value;
                }
            }

            throw new \DomainException(error_get_last() ? error_get_last()['message'] : 'Failed to unserialize values.');
        } catch (\Error $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), \E_ERROR, $e->getFile(), $e->getLine());
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }

    private function getSigningKey(): ?string
    {
        if ($this->signingKey instanceof \Closure) {
            $this->signingKey = self::checkSigningKey(($this->signingKey)());
        }

        return $this->signingKey;
    }

    private static function checkSigningKey(#[\SensitiveParameter] string $signingKey): string
    {
        if (32 > \strlen($signingKey)) {
            throw new InvalidArgumentException('The phpser signing key must be at least 32 bytes long.');
        }

        return $signingKey;
    }

    /**
     * phpser calls unserialize_callback_func for missing classes since v0.10.3,
     * which makes handleUnserializeCallback() turn them into cache misses.
     */
    private static function isPhpSerLoaded(): bool
    {
        return \extension_loaded('phpser') && version_compare(phpversion('phpser'), '0.10.3', '>=');
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new \DomainException('Class not found: '.$class);
    }
}
