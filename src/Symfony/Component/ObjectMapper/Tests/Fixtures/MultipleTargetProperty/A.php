<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MultipleTargetProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[Map(target: B::class)]
#[Map(target: C::class)]
class A
{
    #[Map(target: 'foo', transform: 'strtoupper', if: new TargetClass(B::class))]
    #[Map(target: 'bar')]
    public string $something = 'test';

    #[Map(target: 'otherFoo', transform: 'strtolower', if: new TargetClass([B::class, C::class]))]
    public string $somethingOther = 'TESTOTHER';

    public string $doesNotExistInTargetB = 'foo';
}
