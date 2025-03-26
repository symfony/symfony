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

use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class RangeToStringValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): string
    {
        return $value[0].'..'.$value[1];
    }

    public static function getStreamValueType(): BuiltinType
    {
        return Type::string();
    }
}
