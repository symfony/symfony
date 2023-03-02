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
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\PropertyPath;

class PropertyPathTest extends TestCase
{
    public function testToString()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference.traversable[index].property', $path->__toString());
    }

    public function testDotIsRequiredBeforeProperty()
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath('[index]property');
    }

    public function testDotCannotBePresentAtTheBeginning()
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath('.property');
    }

    public static function providePathsContainingUnexpectedCharacters()
    {
        return [
            ['property.'],
            ['property.['],
            ['property..'],
            ['property['],
            ['property[['],
            ['property[.'],
            ['property[]'],
        ];
    }

    /**
     * @dataProvider providePathsContainingUnexpectedCharacters
     */
    public function testUnexpectedCharacters($path)
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath($path);
    }

    public function testPathCannotBeEmpty()
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath('');
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

    public function testGetElementsWithEscapedDot()
    {
        $propertyPath = new PropertyPath('grandpa\.parent.child');

        $this->assertEquals(['grandpa.parent', 'child'], $propertyPath->getElements());
    }

    public function testGetElementsWithEscapedArray()
    {
        $propertyPath = new PropertyPath('grandpa\[parent][child]');

        $this->assertEquals(['grandpa[parent]', 'child'], $propertyPath->getElements());
    }

    public function testGetElementsWithDoubleEscapedDot()
    {
        $propertyPath = new PropertyPath('grandpa\\\.par\ent.\\\child');

        $this->assertEquals(['grandpa\\', 'par\ent', '\\\child'], $propertyPath->getElements());
    }

    public function testGetElementsWithDoubleEscapedArray()
    {
        $propertyPath = new PropertyPath('grandpa\\\[par\ent][\\\child]');

        $this->assertEquals(['grandpa\\', 'par\ent', '\\\child'], $propertyPath->getElements());
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

    public function testGetElementDoesNotAcceptInvalidIndices()
    {
        $this->expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(3);
    }

    public function testGetElementDoesNotAcceptNegativeIndices()
    {
        $this->expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(-1);
    }

    public function testIsProperty()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertTrue($propertyPath->isProperty(1));
        $this->assertFalse($propertyPath->isProperty(2));
    }

    public function testIsPropertyDoesNotAcceptInvalidIndices()
    {
        $this->expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(3);
    }

    public function testIsPropertyDoesNotAcceptNegativeIndices()
    {
        $this->expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(-1);
    }

    public function testIsIndex()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertFalse($propertyPath->isIndex(1));
        $this->assertTrue($propertyPath->isIndex(2));
    }

    public function testIsIndexDoesNotAcceptInvalidIndices()
    {
        $this->expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(3);
    }

    public function testIsIndexDoesNotAcceptNegativeIndices()
    {
        $this->expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(-1);
    }
}
