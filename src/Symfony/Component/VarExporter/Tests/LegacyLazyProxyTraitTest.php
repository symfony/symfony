<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Symfony\Component\VarExporter\LazyProxyTrait;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\FinalPublicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestOverwritePropClass;

#[IgnoreDeprecations]
#[Group('legacy')]
#[RequiresPhp('<8.4')]
class LegacyLazyProxyTraitTest extends LazyProxyTraitTest
{
    public function testLazyDecoratorClass()
    {
        $obj = new class extends TestClass {
            use LazyProxyTrait {
                createLazyProxy as private;
            }

            public function __construct()
            {
                self::createLazyProxy(fn () => new TestClass((object) ['foo' => 123]), $this);
            }
        };

        $this->assertSame(['foo' => 123], (array) $obj->getDep());
    }

    public function testFinalPublicClass()
    {
        $proxy = $this->createLazyProxy(FinalPublicClass::class, fn () => new FinalPublicClass());

        $this->assertSame(1, $proxy->increment());
        $this->assertSame(2, $proxy->increment());
        $this->assertSame(1, $proxy->decrement());
    }

    public function testOverwritePropClass()
    {
        $proxy = $this->createLazyProxy(TestOverwritePropClass::class, fn () => new TestOverwritePropClass('123', 5));

        $this->assertSame('123', $proxy->getDep());
        $this->assertSame(1, $proxy->increment());
    }
}
