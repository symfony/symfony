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

    /**
     * @param bool|null   $useIgbinarySerialize Passing true is deprecated since Symfony 8.2; pass false to force native PHP serialization
     * @param string|null $signingKey           A key of at least 32 bytes to sign and verify phpser values
     */
    public function __construct(
        ?bool $useIgbinarySerialize = null,
        bool $throwOnSerializationFailure = false,
        #[\SensitiveParameter] private ?string $signingKey = null,
    ) {
        if (null !== $signingKey) {
            if (!\extension_loaded('phpser')) {
                throw new CacheException('The "phpser" PHP extension is not loaded.');
            }
            if (32 > \strlen($signingKey)) {
                throw new InvalidArgumentException('The phpser signing key must be at least 32 bytes long.');
            }
            if ($useIgbinarySerialize) {
                throw new InvalidArgumentException('A phpser signing key cannot be used with igbinary serialization.');
            }
        }
        if ($useIgbinarySerialize) {
            trigger_deprecation('symfony/cache', '8.2', 'Using igbinary in "%s" is deprecated, use the "phpser" extension instead.', self::class);
        }
        if ($useIgbinarySerialize && (!\extension_loaded('igbinary') || version_compare('3.1.6', phpversion('igbinary'), '>'))) {
            throw new CacheException(\extension_loaded('igbinary') ? 'Please upgrade the "igbinary" PHP extension to v3.1.6 or higher.' : 'The "igbinary" PHP extension is not loaded.');
        }
        $this->useIgbinarySerialize = true === $useIgbinarySerialize;
        $this->usePhpSerSerialize = null !== $signingKey || (null === $useIgbinarySerialize && \extension_loaded('phpser'));
        $this->throwOnSerializationFailure = $throwOnSerializationFailure;
    }

    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = $failed = [];

        foreach ($values as $id => $value) {
            try {
                if ($this->useIgbinarySerialize) {
                    $serialized[$id] = igbinary_serialize($value);
                } elseif (null !== $this->signingKey) {
                    $serialized[$id] = phpser_serialize_signed($value, $this->signingKey);
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
            if (null !== $this->signingKey) {
                try {
                    return phpser_unserialize_signed($value, $this->signingKey);
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
            if ("\x01" === ($value[0] ?? '') || "\x02" === ($value[0] ?? '')) {
                if (!\extension_loaded('phpser')) {
                    throw new \RuntimeException('Failed to unserialize values, did you forget to install the "phpser" extension?');
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

    /**
     * @internal
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new \DomainException('Class not found: '.$class);
    }
}
