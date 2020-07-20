<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NullExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AbstractPropertyInfoExtractorTest extends TestCase
{
    /**
     * @var PropertyInfoExtractor
     */
    protected $propertyInfo;

    protected function setUp(): void
    {
        $extractors = [new NullExtractor(), new DummyExtractor()];
        $this->propertyInfo = new PropertyInfoExtractor($extractors, $extractors, $extractors, $extractors, $extractors);
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf(PropertyInitializableExtractorInterface::class, $this->propertyInfo);
    }

    public function testGetShortDescription()
    {
        $this->assertSame('short', $this->propertyInfo->getShortDescription('Foo', 'bar', []));
    }

    public function testGetLongDescription()
    {
        $this->assertSame('long', $this->propertyInfo->getLongDescription('Foo', 'bar', []));
    }

    public function testGetTypes()
    {
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_INT)], $this->propertyInfo->getTypes('Foo', 'bar', []));
    }

    public function testIsReadable()
    {
        $this->assertTrue($this->propertyInfo->isReadable('Foo', 'bar', []));
    }

    public function testIsWritable()
    {
        $this->assertTrue($this->propertyInfo->isWritable('Foo', 'bar', []));
    }

    public function testGetProperties()
    {
        $this->assertEquals(['a', 'b'], $this->propertyInfo->getProperties('Foo'));
    }

    public function testIsInitializable()
    {
        $this->assertTrue($this->propertyInfo->isInitializable('Foo', 'bar', []));
    }
}
