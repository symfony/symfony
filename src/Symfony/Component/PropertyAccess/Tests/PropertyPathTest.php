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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\PropertyPath;

class PropertyPathTest extends TestCase
{
    public function testToString(): void
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference.traversable[index].property', $path->__toString());
    }

    public function testDotIsRequiredBeforeProperty(): void
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath('[index]property');
    }

    public function testDotCannotBePresentAtTheBeginning(): void
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

    #[DataProvider('providePathsContainingUnexpectedCharacters')]
    public function testUnexpectedCharacters(string $path): void
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath($path);
    }

    public function testPathCannotBeEmpty(): void
    {
        $this->expectException(InvalidPropertyPathException::class);
        new PropertyPath('');
    }

    public function testZeroIsValidPropertyPath(): void
    {
        $propertyPath = new PropertyPath('0');

        $this->assertSame('0', (string) $propertyPath);
    }

    public function testGetParentWithDot(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent.child');

        $this->assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetElementsWithEscapedDot(): void
    {
        $propertyPath = new PropertyPath('grandpa\.parent.child');

        $this->assertEquals(['grandpa.parent', 'child'], $propertyPath->getElements());
    }

    public function testGetElementsWithEscapedArray(): void
    {
        $propertyPath = new PropertyPath('grandpa\[parent][child]');

        $this->assertEquals(['grandpa[parent]', 'child'], $propertyPath->getElements());
    }

    public function testGetElementsWithDoubleEscapedDot(): void
    {
        $propertyPath = new PropertyPath('grandpa\\\.par\ent.\\\child');

        $this->assertEquals(['grandpa\\', 'par\ent', '\\\child'], $propertyPath->getElements());
    }

    public function testGetElementsWithDoubleEscapedArray(): void
    {
        $propertyPath = new PropertyPath('grandpa\\\[par\ent][\\\child]');

        $this->assertEquals(['grandpa\\', 'par\ent', '\\\child'], $propertyPath->getElements());
    }

    public function testGetParentWithIndex(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParentWhenThereIsNoParent(): void
    {
        $propertyPath = new PropertyPath('path');

        $this->assertNull($propertyPath->getParent());
    }

    public function testCopyConstructor(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');
        $copy = new PropertyPath($propertyPath);

        $this->assertEquals($propertyPath, $copy);
    }

    public function testGetElement(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertEquals('child', $propertyPath->getElement(2));
    }

    public function testGetElementDoesNotAcceptInvalidIndices(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->expectException(\OutOfBoundsException::class);

        $propertyPath->getElement(3);
    }

    public function testGetElementDoesNotAcceptNegativeIndices(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->expectException(\OutOfBoundsException::class);

        $propertyPath->getElement(-1);
    }

    public function testIsProperty(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertTrue($propertyPath->isProperty(1));
        $this->assertFalse($propertyPath->isProperty(2));
    }

    public function testIsPropertyDoesNotAcceptInvalidIndices(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->expectException(\OutOfBoundsException::class);

        $propertyPath->isProperty(3);
    }

    public function testIsPropertyDoesNotAcceptNegativeIndices(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->expectException(\OutOfBoundsException::class);

        $propertyPath->isProperty(-1);
    }

    public function testIsIndex(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertFalse($propertyPath->isIndex(1));
        $this->assertTrue($propertyPath->isIndex(2));
    }

    public function testIsIndexDoesNotAcceptInvalidIndices(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->expectException(\OutOfBoundsException::class);

        $propertyPath->isIndex(3);
    }

    public function testIsIndexDoesNotAcceptNegativeIndices(): void
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->expectException(\OutOfBoundsException::class);

        $propertyPath->isIndex(-1);
    }
}
