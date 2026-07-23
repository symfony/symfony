<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResettableServicePass as BaseResettableServicePass;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;

#[Group('legacy')]
#[IgnoreDeprecations]
class ResettableServicePassTest extends TestCase
{
    public function testDeprecatedClassExtendsNewClass()
    {
        $this->assertTrue(is_subclass_of(ResettableServicePass::class, BaseResettableServicePass::class));
    }
}
