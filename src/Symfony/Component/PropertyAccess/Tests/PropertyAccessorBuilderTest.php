<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;

class PropertyAccessorBuilderTest extends TestCase
{
    /**
     * @var PropertyAccessorBuilder
     */
    protected $builder;

    protected function setUp(): void
    {
        $this->builder = new PropertyAccessorBuilder();
    }

    protected function tearDown(): void
    {
        $this->builder = null;
    }

    public function testEnableMagicCall()
    {
        $this->assertSame($this->builder, $this->builder->enableMagicCall());
    }

    public function testDisableMagicCall()
    {
        $this->assertSame($this->builder, $this->builder->disableMagicCall());
    }

    public function testIsMagicCallEnable()
    {
        $this->assertFalse($this->builder->isMagicCallEnabled());
        $this->assertTrue($this->builder->enableMagicCall()->isMagicCallEnabled());
        $this->assertFalse($this->builder->disableMagicCall()->isMagicCallEnabled());
    }

    public function testGetPropertyAccessor()
    {
        $this->assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
        $this->assertInstanceOf(PropertyAccessor::class, $this->builder->enableMagicCall()->getPropertyAccessor());
    }

    public function testUseCache()
    {
        $cacheItemPool = new ArrayAdapter();
        $this->builder->setCacheItemPool($cacheItemPool);
        $this->assertEquals($cacheItemPool, $this->builder->getCacheItemPool());
        $this->assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
    }
}
