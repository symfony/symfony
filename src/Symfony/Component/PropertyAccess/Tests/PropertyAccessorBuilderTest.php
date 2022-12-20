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
        self::assertSame($this->builder, $this->builder->enableMagicGet());
        self::assertTrue($this->builder->isMagicGetEnabled());
    }

    public function testDisableMagicGet()
    {
        self::assertSame($this->builder, $this->builder->disableMagicGet());
        self::assertFalse($this->builder->disableMagicGet()->isMagicGetEnabled());
    }

    public function testEnableMagicSet()
    {
        self::assertSame($this->builder, $this->builder->enableMagicSet());
        self::assertTrue($this->builder->isMagicSetEnabled());
    }

    public function testDisableMagicSet()
    {
        self::assertSame($this->builder, $this->builder->disableMagicSet());
        self::assertFalse($this->builder->disableMagicSet()->isMagicSetEnabled());
    }

    public function testEnableMagicCall()
    {
        self::assertSame($this->builder, $this->builder->enableMagicCall());
        self::assertTrue($this->builder->isMagicCallEnabled());
    }

    public function testDisableMagicCall()
    {
        self::assertSame($this->builder, $this->builder->disableMagicCall());
        self::assertFalse($this->builder->isMagicCallEnabled());
    }

    public function testTogglingMagicGet()
    {
        self::assertTrue($this->builder->isMagicGetEnabled());
        self::assertFalse($this->builder->disableMagicGet()->isMagicGetEnabled());
        self::assertTrue($this->builder->enableMagicGet()->isMagicGetEnabled());
    }

    public function testTogglingMagicSet()
    {
        self::assertTrue($this->builder->isMagicSetEnabled());
        self::assertFalse($this->builder->disableMagicSet()->isMagicSetEnabled());
        self::assertTrue($this->builder->enableMagicSet()->isMagicSetEnabled());
    }

    public function testTogglingMagicCall()
    {
        self::assertFalse($this->builder->isMagicCallEnabled());
        self::assertTrue($this->builder->enableMagicCall()->isMagicCallEnabled());
        self::assertFalse($this->builder->disableMagicCall()->isMagicCallEnabled());
    }

    public function testGetPropertyAccessor()
    {
        self::assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
        self::assertInstanceOf(PropertyAccessor::class, $this->builder->enableMagicCall()->getPropertyAccessor());
    }

    public function testUseCache()
    {
        $cacheItemPool = new ArrayAdapter();
        $this->builder->setCacheItemPool($cacheItemPool);
        self::assertEquals($cacheItemPool, $this->builder->getCacheItemPool());
        self::assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
    }

    public function testUseReadInfoExtractor()
    {
        $readInfoExtractor = self::createMock(PropertyReadInfoExtractorInterface::class);

        $this->builder->setReadInfoExtractor($readInfoExtractor);

        self::assertSame($readInfoExtractor, $this->builder->getReadInfoExtractor());
        self::assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
    }

    public function testUseWriteInfoExtractor()
    {
        $writeInfoExtractor = self::createMock(PropertyWriteInfoExtractorInterface::class);

        $this->builder->setWriteInfoExtractor($writeInfoExtractor);

        self::assertSame($writeInfoExtractor, $this->builder->getWriteInfoExtractor());
        self::assertInstanceOf(PropertyAccessor::class, $this->builder->getPropertyAccessor());
    }
}
