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
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

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

    public function testEnableMagicGet()
    {
        $this->assertSame($this->builder, $this->builder->enableMagicGet());
        $this->assertTrue($this->builder->isMagicGetEnabled());
    }

    public function testDisableMagicGet()
    {
        $this->assertSame($this->builder, $this->builder->disableMagicGet());
        $this->assertFalse($this->builder->disableMagicGet()->isMagicGetEnabled());
    }

    public function testEnableMagicSet()
    {
        $this->assertSame($this->builder, $this->builder->enableMagicSet());
        $this->assertTrue($this->builder->isMagicSetEnabled());
    }

    public function testDisableMagicSet()
    {
        $this->assertSame($this->builder, $this->builder->disableMagicSet());
        $this->assertFalse($this->builder->disableMagicSet()->isMagicSetEnabled());
    }

    public function testEnableMagicCall()
    {
        $this->assertSame($this->builder, $this->builder->enableMagicCall());
        $this->assertTrue($this->builder->isMagicCallEnabled());
    }

    public function testDisableMagicCall()
    {
        $this->assertSame($this->builder, $this->builder->disableMagicCall());
        $this->assertFalse($this->builder->isMagicCallEnabled());
    }

    public function testTogglingMagicGet()
    {
        $this->assertTrue($this->builder->isMagicGetEnabled());
        $this->assertFalse($this->builder->disableMagicGet()->isMagicGetEnabled());
        $this->assertTrue($this->builder->enableMagicGet()->isMagicGetEnabled());
    }

    public function testTogglingMagicSet()
    {
        $this->assertTrue($this->builder->isMagicSetEnabled());
        $this->assertFalse($this->builder->disableMagicSet()->isMagicSetEnabled());
        $this->assertTrue($this->builder->enableMagicSet()->isMagicSetEnabled());
    }

    public function testTogglingMagicCall()
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

    public function testUseReadInfoExtractor()
    {
        $readInfoExtractor = $this->createMock(PropertyReadInfoExtractorInterface::class);

        $this->builder->setReadInfoExtractor($readInfoExtractor);

        $this->assertSame($readInfoExtractor, $this->builder->getReadInfoExtractor());
        $this->assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
    }

    public function testUseWriteInfoExtractor()
    {
        $writeInfoExtractor = $this->createMock(PropertyWriteInfoExtractorInterface::class);

        $this->builder->setWriteInfoExtractor($writeInfoExtractor);

        $this->assertSame($writeInfoExtractor, $this->builder->getWriteInfoExtractor());
        $this->assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
    }
}
