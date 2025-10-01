<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TargetTransform;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: SourceEntity::class, transform: [self::class, 't'])]
class TargetDto
{
    #[Map(if: false)]
    public bool $transformed;
    public string $name;

    public static function t(mixed $value, object $source, ?object $target)
    {
        $value->transformed = true;

        return $value;
    }
}
