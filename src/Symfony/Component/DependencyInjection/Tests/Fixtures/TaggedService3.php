<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomAutoconfiguration;

#[CustomAutoconfiguration(someAttribute: 'three')]
final class TaggedService3
{
    public int $sum = 0;
    public bool $hasBeenConfigured = false;

    public function __construct(
        public string $foo,
    ) {
    }

    public function doSomething(int $a, int $b, int $c): void
    {
        $this->sum = $a + $b + $c;
    }
}
