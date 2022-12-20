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

abstract class PropertyAccessorArrayAccessTest extends TestCase
{
    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    protected function setUp(): void
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    abstract protected function getContainer(array $array);

    public function getValidPropertyPaths()
    {
        return [
            [$this->getContainer(['firstName' => 'Bernhard']), '[firstName]', 'Bernhard'],
            [$this->getContainer(['person' => $this->getContainer(['firstName' => 'Bernhard'])]), '[person][firstName]', 'Bernhard'],
        ];
    }

    public function getInvalidPropertyPaths()
    {
        return [
            [$this->getContainer(['firstName' => 'Bernhard']), 'firstName', 'Bernhard'],
            [$this->getContainer(['person' => $this->getContainer(['firstName' => 'Bernhard'])]), 'person.firstName', 'Bernhard'],
        ];
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue($collection, $path, $value)
    {
        self::assertSame($value, $this->propertyAccessor->getValue($collection, $path));
    }

    public function testGetValueFailsIfNoSuchIndex()
    {
        self::expectException(NoSuchIndexException::class);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->getPropertyAccessor();

        $object = $this->getContainer(['firstName' => 'Bernhard']);

        $this->propertyAccessor->getValue($object, '[lastName]');
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue($collection, $path)
    {
        $this->propertyAccessor->setValue($collection, $path, 'Updated');

        self::assertSame('Updated', $this->propertyAccessor->getValue($collection, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsReadable($collection, $path)
    {
        self::assertTrue($this->propertyAccessor->isReadable($collection, $path));
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testIsWritable($collection, $path)
    {
        self::assertTrue($this->propertyAccessor->isWritable($collection, $path));
    }

    /**
     * @dataProvider getInvalidPropertyPaths
     */
    public function testIsNotWritable($collection, $path)
    {
        self::assertFalse($this->propertyAccessor->isWritable($collection, $path));
    }
}
