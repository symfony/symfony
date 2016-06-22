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

use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NullExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AbstractPropertyInfoExtractorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyInfoExtractor
     */
    protected $propertyInfo;

    protected function setUp()
    {
        $extractors = array(new NullExtractor(), new DummyExtractor());
        $this->propertyInfo = new PropertyInfoExtractor($extractors, $extractors, $extractors, $extractors);
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface', $this->propertyInfo);
        $this->assertInstanceOf('Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface', $this->propertyInfo);
    }

    public function testGetShortDescription()
    {
        $this->assertSame('short', $this->propertyInfo->getShortDescription('Foo', 'bar', array()));
    }

    public function testGetLongDescription()
    {
        $this->assertSame('long', $this->propertyInfo->getLongDescription('Foo', 'bar', array()));
    }

    public function testGetTypes()
    {
        $this->assertEquals(array(new Type(Type::BUILTIN_TYPE_INT)), $this->propertyInfo->getTypes('Foo', 'bar', array()));
    }

    public function testIsReadable()
    {
        $this->assertTrue($this->propertyInfo->isReadable('Foo', 'bar', array()));
    }

    public function testIsWritable()
    {
        $this->assertTrue($this->propertyInfo->isWritable('Foo', 'bar', array()));
    }

    public function testGetProperties()
    {
        $this->assertEquals(array('a', 'b'), $this->propertyInfo->getProperties('Foo'));
    }
}
