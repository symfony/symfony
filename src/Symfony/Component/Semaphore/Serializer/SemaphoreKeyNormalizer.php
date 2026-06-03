<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Serializer;

use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes {@see Key} instances for transferring between processes.
 *
 * Trust boundary: the payload accepted by {@see denormalize()} is the
 * public-array shape documented on {@see Key::__serialize()}. The
 * normalizer performs shape and type validation and converts construction
 * failures to {@see NotNormalizableValueException} so HTTP boundaries can
 * catch a single exception type. The opaque `state` map is forwarded to the
 * Key as-is; callers transferring keys across a trust boundary are
 * responsible for validating that map independently when its contents are
 * security-sensitive.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class SemaphoreKeyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Key::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return $data->__serialize();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Key;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Key
    {
        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Expected an array to denormalize "%s".', Key::class), $data, ['array']);
        }

        foreach (['resource' => 'string', 'limit' => 'int', 'weight' => 'int'] as $field => $expectedType) {
            if (!\array_key_exists($field, $data)) {
                throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Missing required field "%s" to denormalize "%s".', $field, Key::class), $data, [$expectedType]);
            }
            if ('string' === $expectedType ? !\is_string($data[$field]) : !\is_int($data[$field])) {
                throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Field "%s" must be of type "%s" to denormalize "%s".', $field, $expectedType, Key::class), $data[$field], [$expectedType]);
            }
        }

        if (isset($data['expiringTime']) && !\is_int($data['expiringTime']) && !\is_float($data['expiringTime'])) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Field "expiringTime" must be of type "float" or null to denormalize "%s".', Key::class), $data['expiringTime'], ['float', 'null']);
        }

        if (isset($data['state']) && !\is_array($data['state'])) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('Field "state" must be of type "array" to denormalize "%s".', Key::class), $data['state'], ['array']);
        }

        $key = (new \ReflectionClass(Key::class))->newInstanceWithoutConstructor();
        try {
            $key->__unserialize($data);
        } catch (InvalidArgumentException $e) {
            throw new NotNormalizableValueException($e->getMessage(), 0, $e);
        } catch (\TypeError $e) {
            throw new NotNormalizableValueException(\sprintf('Failed to denormalize "%s": %s', Key::class, $e->getMessage()), 0, $e);
        }

        return $key;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Key::class === $type;
    }
}
