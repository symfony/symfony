<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Tests\Fixtures;

use Symfony\Component\Mapper\Attributes\Map;

#[Map(B::class)]
class A
{
    #[Map('bar')]
    public string $foo;

    public string $baz;

    public string $notinb;

    #[Map(transform: 'strtoupper')]
    public string $transform;

    #[Map(transform: [self::class, 'concatFn'])]
    public ?string $concat = null;

    #[Map(if: 'boolval')]
    public bool $nomap = false;

    public C $relation;

    public D $relationNotMapped;

    public function getConcat()
    {
        return 'should';
    }

    public static function concatFn($v, $object): string
    {
        return $v.$object->foo.$object->baz;
    }
}
