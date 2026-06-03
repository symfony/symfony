<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleSourceProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\SourceClass;

#[Map(source: B::class)]
#[Map(source: C::class)]
class A
{
    #[Map(source: 'foo', transform: 'strtolower', if: new SourceClass(B::class))]
    #[Map(source: 'bar', if: new SourceClass(C::class))]
    public string $something;

    #[Map(source: 'foo', transform: 'strtoupper', if: new SourceClass([B::class, C::class]))]
    public string $somethingOther;

    public string $doesNotExistInSourceB;
}
