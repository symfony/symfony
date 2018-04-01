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
use Symphony\Component\PropertyAccess\PropertyPath;

class PropertyPathTest extends TestCase
{
    public function testToString()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference.traversable[index].property', $path->__toString());
    }

    /**
     * @expectedException \Symphony\Component\PropertyAccess\Exception\InvalidPropertyPathException
     */
    public function testDotIsRequiredBeforeProperty()
    {
        new PropertyPath('[index]property');
    }

    /**
     * @expectedException \Symphony\Component\PropertyAccess\Exception\InvalidPropertyPathException
     */
    public function testDotCannotBePresentAtTheBeginning()
    {
        new PropertyPath('.property');
    }

    public function providePathsContainingUnexpectedCharacters()
    {
        return array(
            array('property.'),
            array('property.['),
            array('property..'),
            array('property['),
            array('property[['),
            array('property[.'),
            array('property[]'),
        );
    }

    /**
     * @dataProvider providePathsContainingUnexpectedCharacters
     * @expectedException \Symphony\Component\PropertyAccess\Exception\InvalidPropertyPathException
     */
    public function testUnexpectedCharacters($path)
    {
        new PropertyPath($path);
    }

    /**
     * @expectedException \Symphony\Component\PropertyAccess\Exception\InvalidPropertyPathException
     */
    public function testPathCannotBeEmpty()
    {
        new PropertyPath('');
    }

    /**
     * @expectedException \Symphony\Component\PropertyAccess\Exception\InvalidArgumentException
     */
    public function testPathCannotBeNull()
    {
        new PropertyPath(null);
    }

    /**
     * @expectedException \Symphony\Component\PropertyAccess\Exception\InvalidArgumentException
     */
    public function testPathCannotBeFalse()
    {
        new PropertyPath(false);
    }

    public function testZeroIsValidPropertyPath()
    {
        $propertyPath = new PropertyPath('0');

        $this->assertSame('0', (string) $propertyPath);
    }

    public function testGetParentWithDot()
    {
        $propertyPath = new PropertyPath('grandpa.parent.child');

        $this->assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParentWithIndex()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParentWhenThereIsNoParent()
    {
        $propertyPath = new PropertyPath('path');

        $this->assertNull($propertyPath->getParent());
    }

    public function testCopyConstructor()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');
        $copy = new PropertyPath($propertyPath);

        $this->assertEquals($propertyPath, $copy);
    }

    public function testGetElement()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertEquals('child', $propertyPath->getElement(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetElementDoesNotAcceptInvalidIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetElementDoesNotAcceptNegativeIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(-1);
    }

    public function testIsProperty()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertTrue($propertyPath->isProperty(1));
        $this->assertFalse($propertyPath->isProperty(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsPropertyDoesNotAcceptInvalidIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsPropertyDoesNotAcceptNegativeIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(-1);
    }

    public function testIsIndex()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertFalse($propertyPath->isIndex(1));
        $this->assertTrue($propertyPath->isIndex(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsIndexDoesNotAcceptInvalidIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsIndexDoesNotAcceptNegativeIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(-1);
    }
}
