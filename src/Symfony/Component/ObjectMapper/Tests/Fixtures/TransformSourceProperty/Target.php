<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformSourceProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Source::class)]
class Target
{
    #[Map(source: 'sourceProperty', transform: [self::class, 'transformProperty'])]
    public string $targetProperty;

    public static function transformProperty(string $value): string
    {
        return $value;
    }
}
