<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Uuid;

final class UidNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public const NORMALIZATION_FORMAT_KEY = 'uid_normalization_format';

    public const NORMALIZATION_FORMAT_CANONICAL = 'canonical';
    public const NORMALIZATION_FORMAT_BASE58 = 'base58';
    public const NORMALIZATION_FORMAT_BASE32 = 'base32';
    public const NORMALIZATION_FORMAT_RFC4122 = 'rfc4122';
    public const NORMALIZATION_FORMATS = [
        self::NORMALIZATION_FORMAT_CANONICAL,
        self::NORMALIZATION_FORMAT_BASE58,
        self::NORMALIZATION_FORMAT_BASE32,
        self::NORMALIZATION_FORMAT_RFC4122,
    ];

    private array $defaultContext = [
        self::NORMALIZATION_FORMAT_KEY => self::NORMALIZATION_FORMAT_CANONICAL,
    ];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AbstractUid::class => true,
        ];
    }

    /**
     * @param AbstractUid $object
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return match ($context[self::NORMALIZATION_FORMAT_KEY] ?? $this->defaultContext[self::NORMALIZATION_FORMAT_KEY]) {
            self::NORMALIZATION_FORMAT_CANONICAL => (string) $object,
            self::NORMALIZATION_FORMAT_BASE58 => $object->toBase58(),
            self::NORMALIZATION_FORMAT_BASE32 => $object->toBase32(),
            self::NORMALIZATION_FORMAT_RFC4122 => $object->toRfc4122(),
            default => throw new LogicException(sprintf('The "%s" format is not valid.', $context[self::NORMALIZATION_FORMAT_KEY] ?? $this->defaultContext[self::NORMALIZATION_FORMAT_KEY])),
        };
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof AbstractUid;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        try {
            if (AbstractUid::class === $type) {
                trigger_deprecation('symfony/serializer', '6.1', 'Denormalizing to an abstract class in "%s" is deprecated.', __CLASS__);

                return Uuid::fromString($data);
            }

            return $type::fromString($data);
        } catch (\InvalidArgumentException|\TypeError) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('The data is not a valid "%s" string representation.', $type), $data, [Type::BUILTIN_TYPE_STRING], $context['deserialization_path'] ?? null, true);
        } catch (\Error $e) { // @deprecated remove this catch block in 7.0
            if (str_starts_with($e->getMessage(), 'Cannot instantiate abstract class')) {
                return $this->denormalize($data, AbstractUid::class, $format, $context);
            }

            throw $e;
        }
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        if (AbstractUid::class === $type) {
            trigger_deprecation('symfony/serializer', '6.1', 'Supporting denormalization for the "%s" type in "%s" is deprecated, use one of "%s" child class instead.', AbstractUid::class, __CLASS__, AbstractUid::class);

            return true;
        }

        return is_subclass_of($type, AbstractUid::class, true);
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, use "getSupportedTypes()" instead.', __METHOD__);

        return true;
    }
}
