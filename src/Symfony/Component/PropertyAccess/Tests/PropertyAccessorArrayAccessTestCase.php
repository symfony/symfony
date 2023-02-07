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
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

abstract class PropertyAccessorArrayAccessTestCase extends TestCase
{
    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    protected function setUp(): void
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    abstract protected static function getContainer(array $array);

    public static function getValidPropertyPaths(): array
    {
        return [
            [static::getContainer(['firstName' => 'Bernhard']), '[firstName]', 'Bernhard'],
            [static::getContainer(['person' => static::getContainer(['firstName' => 'Bernhard'])]), '[person][firstName]', 'Bernhard'],
        ];
    }

    public static function getInvalidPropertyPaths(): array
    {
        return [
            [static::getContainer(['firstName' => 'Bernhard']), 'firstName', 'Bernhard'],
            [static::getContainer(['person' => static::getContainer(['firstName' => 'Bernhard'])]), 'person.firstName', 'Bernhard'],
        ];
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue($collection, $path, $value)
    {
        $this->assertSame($value, $this->propertyAccessor->getValue($collection, $path));
    }

    public function testGetValueFailsIfNoSuchIndex()
    {
        $this->expectException(NoSuchIndexException::class);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();

        $object = static::getContainer(['firstName' => 'Bernhard']);

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

    /**
     * @dataProvider getInvalidPropertyPaths
     */
    public function testIsNotWritable($collection, $path)
    {
        $this->assertFalse($this->propertyAccessor->isWritable($collection, $path));
    }
}
