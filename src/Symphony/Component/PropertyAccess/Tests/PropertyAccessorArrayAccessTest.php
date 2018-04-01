<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\PropertyAccess\PropertyAccess;
use Symphony\Component\PropertyAccess\PropertyAccessor;

abstract class PropertyAccessorArrayAccessTest extends TestCase
{
    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    protected function setUp()
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    abstract protected function getContainer(array $array);

    public function getValidPropertyPaths()
    {
        return array(
            array($this->getContainer(array('firstName' => 'Bernhard')), '[firstName]', 'Bernhard'),
            array($this->getContainer(array('person' => $this->getContainer(array('firstName' => 'Bernhard')))), '[person][firstName]', 'Bernhard'),
        );
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue($collection, $path, $value)
    {
        $this->assertSame($value, $this->propertyAccessor->getValue($collection, $path));
    }

    /**
     * @expectedException \Symphony\Component\PropertyAccess\Exception\NoSuchIndexException
     */
    public function testGetValueFailsIfNoSuchIndex()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();

        $object = $this->getContainer(array('firstName' => 'Bernhard'));

        $this->propertyAccessor->getValue($object, '[lastName]');
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue($collection, $path)
    {
        $this->propertyAccessor->setValue($collection, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($collection, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsReadable($collection, $path)
    {
        $this->assertTrue($this->propertyAccessor->isReadable($collection, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsWritable($collection, $path)
    {
        $this->assertTrue($this->propertyAccessor->isWritable($collection, $path));
    }
}
