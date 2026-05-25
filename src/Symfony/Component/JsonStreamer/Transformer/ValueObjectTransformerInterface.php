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

use Symfony\Component\TypeInfo\Type\BuiltinType;

/**
 * Transforms value objects during stream writing and reading.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @template T of object
 * @template U of int|float|string|bool|null
 */
interface ValueObjectTransformerInterface
{
    /**
     * Transforms a value object into a scalar value.
     *
     * @param T                    $object
     * @param array<string, mixed> $options
     *
     * @return U
     */
    public function transform(object $object, array $options = []): int|float|string|bool|null;

    /**
     * Reverses the transformation from a scalar value back into a value object.
     *
     * @param U                    $scalar
     * @param array<string, mixed> $options
     *
     * @return T
     */
    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): object;

    public static function getStreamValueType(): BuiltinType;

    /**
     * @return class-string<T>
     */
    public static function getValueObjectClassName(): string;
}
