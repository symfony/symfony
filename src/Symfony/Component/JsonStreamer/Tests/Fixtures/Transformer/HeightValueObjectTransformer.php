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

use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueObject\Height;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

/**
 * @implements ValueObjectTransformerInterface<Height, string>
 */
class HeightValueObjectTransformer implements ValueObjectTransformerInterface
{
    public function transform(object $object, array $options = []): int|float|string|bool|null
    {
        return $object->value.' '.$object->unit;
    }

    public function reverseTransform(int|float|string|bool|null $scalar, array $options = []): object
    {
        return new Height(...explode(' ', $scalar));
    }

    public static function getStreamValueType(): BuiltinType
    {
        return Type::string();
    }

    public static function getValueObjectClassName(): string
    {
        return Height::class;
    }
}
