<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: SharedSource::class)]
final class SharedTargetWithTransform
{
    #[Map(source: 'value', transform: [self::class, 'double'])]
    public int $value;

    public static function double(mixed $v): int
    {
        return $v * 2;
    }
}
