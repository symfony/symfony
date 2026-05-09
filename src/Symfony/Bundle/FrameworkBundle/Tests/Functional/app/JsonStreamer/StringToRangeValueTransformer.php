<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonStreamer;

use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

// BC layer for "symfony/json-streamer" < 8.1
if (!interface_exists(PropertyValueTransformerInterface::class)) {
    class StringToRangeValueTransformer implements ValueTransformerInterface
    {
        public function transform(mixed $value, array $options = []): array
        {
            return array_map(static fn (string $v): int => (int) $v, explode('..', $value));
        }

        public static function getStreamValueType(): BuiltinType
        {
            return Type::string();
        }
    }
} else {
    class StringToRangeValueTransformer implements PropertyValueTransformerInterface
    {
        public function transform(mixed $value, array $options = []): array
        {
            return array_map(static fn (string $v): int => (int) $v, explode('..', $value));
        }

        public static function getStreamValueType(): BuiltinType
        {
            return Type::string();
        }
    }
}
