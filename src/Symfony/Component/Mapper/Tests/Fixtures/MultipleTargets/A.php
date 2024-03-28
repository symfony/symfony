<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Tests\Fixtures\MultipleTargets;

use Symfony\Component\Mapper\Attributes\Map;

#[Map(to: B::class, if: [A::class, 'shouldMapToB'])]
#[Map(to: C::class, if: [A::class, 'shouldMapToC'])]
class A
{
    public function __construct(public readonly string $foo = 'bar')
    {
    }

    public static function shouldMapToB(mixed $value, object $object): bool
    {
        return false;
    }

    public static function shouldMapToC(mixed $value, object $object): bool
    {
        return true;
    }
}
