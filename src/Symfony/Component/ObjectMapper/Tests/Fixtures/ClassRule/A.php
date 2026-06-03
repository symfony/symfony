<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassRule;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\ClassRule;
use Symfony\Component\ObjectMapper\Condition\ClassRuleList;
use Symfony\Component\ObjectMapper\Condition\SourceClass;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[Map(source: B::class)]
#[Map(source: C::class)]
#[Map(target: B::class)]
#[Map(target: C::class)]
class A
{
    #[Map(
        source: 'foo',
        transform: 'strtolower',
        if: new ClassRuleList([
            new SourceClass(B::class),
        ]),
    )]
    #[Map(
        source: 'bar',
        if: new ClassRuleList([
            new ClassRule(sources: [C::class]),
        ]),
    )]
    public string $somethingSourced;

    #[Map(
        target: 'foo',
        transform: 'strtoupper',
        if: new ClassRuleList([
            new TargetClass(B::class),
        ]),
    )]
    #[Map(
        target: 'bar',
        if: new ClassRuleList([
            new ClassRule(targets: [C::class]),
        ]),
    )]
    public string $somethingTargeted = 'testTargeted';
}
