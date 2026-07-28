<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Transformer;

use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\Exception\InvalidArgumentException as UidInvalidArgumentException;
use Symfony\Component\Uid\Ulid;

/**
 * Transforms a {@see Ulid} to a string and vice versa.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @psalm-type Options = array{
 *     uid_format?: self::FORMAT_*,
 *     ...<string, mixed>,
 * }
 *
 * @implements ValueObjectTransformerInterface<Ulid, string>
 */
final class UlidValueObjectTransformer implements ValueObjectTransformerInterface
{
    public const FORMAT_KEY = 'uid_format';

    public const FORMAT_CANONICAL = 'canonical';
    public const FORMAT_BASE58 = 'base58';
    public const FORMAT_BASE32 = 'base32';
    public const FORMAT_RFC4122 = 'rfc4122';
    public const FORMAT_RFC9562 = self::FORMAT_RFC4122; // RFC 9562 obsoleted RFC 4122 but the format is the same

    /**
     * @param Options $options
     */
    public function transform(object $object, array $options = []): string
    {
        if (!$object instanceof Ulid) {
            throw new InvalidArgumentException(\sprintf('The native value must be an instance of "%s".', Ulid::class));
        }

        $format = $options[self::FORMAT_KEY] ?? self::FORMAT_CANONICAL;

        return match ($format) {
            self::FORMAT_CANONICAL => (string) $object,
            self::FORMAT_BASE58 => $object->toBase58(),
            self::FORMAT_BASE32 => $object->toBase32(),
            self::FORMAT_RFC4122 => $object->toRfc4122(),
            default => throw new InvalidArgumentException(\sprintf('The "%s" format is not valid.', $format)),
        };
    }

    /**
     * @return Ulid
     */
    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): object
    {
        if (!\is_string($scalar)) {
            throw new InvalidArgumentException(\sprintf('The JSON value must be a string, "%s" given.', get_debug_type($scalar)));
        }

        try {
            return Ulid::fromString($scalar);
        } catch (UidInvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('The string "%s" is not a valid ULID.', $scalar), $e->getCode(), $e);
        }
    }

    /**
     * @return BuiltinType<TypeIdentifier::STRING>
     */
    public static function getStreamValueType(): BuiltinType
    {
        return Type::string();
    }

    public static function getValueObjectClassName(): string
    {
        return Ulid::class;
    }
}
