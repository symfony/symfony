<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Type;

use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Represents a literal value, such as "'draft'" or "1".
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @template T of int|float|string
 *
 * @extends Type<T>
 *
 * @implements WrappingTypeInterface<BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::FLOAT>|BuiltinType<TypeIdentifier::STRING>>
 */
final class LiteralType extends Type implements WrappingTypeInterface
{
    /**
     * @var BuiltinType<TypeIdentifier::INT>|BuiltinType<TypeIdentifier::FLOAT>|BuiltinType<TypeIdentifier::STRING>
     */
    private readonly BuiltinType $type;

    /**
     * @param T $value
     */
    public function __construct(
        private readonly int|float|string $value,
    ) {
        if (\is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException(\sprintf('Cannot create "%s" with a non-finite float.', self::class));
        }

        $this->type = match (true) {
            \is_int($value) => Type::int(),
            \is_float($value) => Type::float(),
            default => Type::string(),
        };
    }

    /**
     * @return T
     */
    public function getValue(): int|float|string
    {
        return $this->value;
    }

    public function getWrappedType(): Type
    {
        return $this->type;
    }

    /**
     * @param-immediately-invoked-callable $specification
     */
    public function wrappedTypeIsSatisfiedBy(callable $specification): bool
    {
        return $this->getWrappedType()->isSatisfiedBy($specification);
    }

    public function accepts(mixed $value): bool
    {
        return $value === $this->value;
    }

    public function __toString(): string
    {
        // a plain union, as the template type is not narrowed by the checks below
        /** @var int|float|string $value */
        $value = $this->value;

        if (\is_int($value)) {
            return (string) $value;
        }

        // var_export() keeps floats such as 1.0 distinguishable from integers
        if (\is_float($value)) {
            return var_export($value, true);
        }

        // escape control characters, double quotes and backslashes using their hexadecimal notation, such as "\x0A" for "\n"
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $escaped = preg_replace_callback('/[\x00-\x1F\x7F"\\\\]/', static fn (array $m): string => \sprintf('\x%02X', \ord($m[0])), $value);

            return '"'.$escaped.'"';
        }

        return "'".addcslashes($value, "'\\")."'";
    }
}
