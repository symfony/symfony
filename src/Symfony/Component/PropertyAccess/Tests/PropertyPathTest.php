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
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\PropertyPath;

class PropertyPathTest extends TestCase
{
    public function testToString()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        self::assertEquals('reference.traversable[index].property', $path->__toString());
    }

    public function testDotIsRequiredBeforeProperty()
    {
        self::expectException(InvalidPropertyPathException::class);
        new PropertyPath('[index]property');
    }

    public function testDotCannotBePresentAtTheBeginning()
    {
        self::expectException(InvalidPropertyPathException::class);
        new PropertyPath('.property');
    }

    public function providePathsContainingUnexpectedCharacters()
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
        self::expectException(InvalidPropertyPathException::class);
        new PropertyPath($path);
    }

    public function testPathCannotBeEmpty()
    {
        self::expectException(InvalidPropertyPathException::class);
        new PropertyPath('');
    }

    public function testPathCannotBeNull()
    {
        self::expectException(InvalidArgumentException::class);
        new PropertyPath(null);
    }

    public function testPathCannotBeFalse()
    {
        self::expectException(InvalidArgumentException::class);
        new PropertyPath(false);
    }

    public function testZeroIsValidPropertyPath()
    {
        $propertyPath = new PropertyPath('0');

        self::assertSame('0', (string) $propertyPath);
    }

    public function testGetParentWithDot()
    {
        $propertyPath = new PropertyPath('grandpa.parent.child');

        self::assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParentWithIndex()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        self::assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParentWhenThereIsNoParent()
    {
        $propertyPath = new PropertyPath('path');

        self::assertNull($propertyPath->getParent());
    }

    public function testCopyConstructor()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');
        $copy = new PropertyPath($propertyPath);

        self::assertEquals($propertyPath, $copy);
    }

    public function testGetElement()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        self::assertEquals('child', $propertyPath->getElement(2));
    }

    public function testGetElementDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(3);
    }

    public function testGetElementDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(-1);
    }

    public function testIsProperty()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        self::assertTrue($propertyPath->isProperty(1));
        self::assertFalse($propertyPath->isProperty(2));
    }

    public function testIsPropertyDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(3);
    }

    public function testIsPropertyDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(-1);
    }

    public function testIsIndex()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        self::assertFalse($propertyPath->isIndex(1));
        self::assertTrue($propertyPath->isIndex(2));
    }

    public function testIsIndexDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(3);
    }

    public function testIsIndexDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(-1);
    }
}
