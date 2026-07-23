<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer;

use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueObjectAndUnion;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @implements ValueObjectTransformerInterface<DummyWithValueObjectAndUnion, mixed>
 */
final class UnsupportedStreamValueTypeObjectTransformer implements ValueObjectTransformerInterface
{
    public function transform(object $object, array $options = []): int|float|string|bool|null
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): object
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * @return BuiltinType<TypeIdentifier::MIXED>
     */
    public static function getStreamValueType(): BuiltinType
    {
        return Type::mixed();
    }

    public static function getValueObjectClassName(): string
    {
        return DummyWithValueObjectAndUnion::class;
    }
}
