<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\ViolationMapper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ViolationPathTest extends TestCase
{
    public function providePaths()
    {
        return [
            ['children[address]', [
                ['address', true, true],
            ]],
            ['children[address].children[street]', [
                ['address', true, true],
                ['street', true, true],
            ]],
            ['children[address][street]', [
                ['address', true, true],
                ['street', true, true],
            ], 'children[address].children[street]'],
            ['children[address].data', [
                ['address', true, true],
            ], 'children[address]'],
            ['children[address].data.street', [
                ['address', true, true],
                ['street', false, false],
            ]],
            ['children[address].data[street]', [
                ['address', true, true],
                ['street', false, true],
            ]],
            ['children[address].children[street].data.name', [
                ['address', true, true],
                ['street', true, true],
                ['name', false, false],
            ]],
            ['children[address].children[street].data[name]', [
                ['address', true, true],
                ['street', true, true],
                ['name', false, true],
            ]],
            ['data.address', [
                ['address', false, false],
            ]],
            ['data[address]', [
                ['address', false, true],
            ]],
            ['data.address.street', [
                ['address', false, false],
                ['street', false, false],
            ]],
            ['data[address].street', [
                ['address', false, true],
                ['street', false, false],
            ]],
            ['data.address[street]', [
                ['address', false, false],
                ['street', false, true],
            ]],
            ['data[address][street]', [
                ['address', false, true],
                ['street', false, true],
            ]],
            // A few invalid examples
            ['data', [], ''],
            ['children', [], ''],
            ['children.address', [], ''],
            ['children.address[street]', [], ''],
        ];
    }

    /**
     * @dataProvider providePaths
     */
    public function testCreatePath($string, $entries, $slicedPath = null)
    {
        if (null === $slicedPath) {
            $slicedPath = $string;
        }

        $path = new ViolationPath($string);

        self::assertSame($slicedPath, $path->__toString());
        self::assertCount(\count($entries), $path->getElements());
        self::assertSame(\count($entries), $path->getLength());

        foreach ($entries as $index => $entry) {
            self::assertEquals($entry[0], $path->getElement($index));
            self::assertSame($entry[1], $path->mapsForm($index));
            self::assertSame($entry[2], $path->isIndex($index));
            self::assertSame(!$entry[2], $path->isProperty($index));
        }
    }

    public function provideParents()
    {
        return [
            ['children[address]', null],
            ['children[address].children[street]', 'children[address]'],
            ['children[address].data.street', 'children[address]'],
            ['children[address].data[street]', 'children[address]'],
            ['data.address', null],
            ['data.address.street', 'data.address'],
            ['data.address[street]', 'data.address'],
            ['data[address].street', 'data[address]'],
            ['data[address][street]', 'data[address]'],
        ];
    }

    /**
     * @dataProvider provideParents
     */
    public function testGetParent($violationPath, $parentPath)
    {
        $path = new ViolationPath($violationPath);
        $parent = null === $parentPath ? null : new ViolationPath($parentPath);

        self::assertEquals($parent, $path->getParent());
    }

    public function testGetElement()
    {
        $path = new ViolationPath('children[address].data[street].name');

        self::assertEquals('street', $path->getElement(1));
    }

    public function testGetElementDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->getElement(3);
    }

    public function testGetElementDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->getElement(-1);
    }

    public function testIsProperty()
    {
        $path = new ViolationPath('children[address].data[street].name');

        self::assertFalse($path->isProperty(1));
        self::assertTrue($path->isProperty(2));
    }

    public function testIsPropertyDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->isProperty(3);
    }

    public function testIsPropertyDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->isProperty(-1);
    }

    public function testIsIndex()
    {
        $path = new ViolationPath('children[address].data[street].name');

        self::assertTrue($path->isIndex(1));
        self::assertFalse($path->isIndex(2));
    }

    public function testIsIndexDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->isIndex(3);
    }

    public function testIsIndexDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->isIndex(-1);
    }

    public function testMapsForm()
    {
        $path = new ViolationPath('children[address].data[street].name');

        self::assertTrue($path->mapsForm(0));
        self::assertFalse($path->mapsForm(1));
        self::assertFalse($path->mapsForm(2));
    }

    public function testMapsFormDoesNotAcceptInvalidIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->mapsForm(3);
    }

    public function testMapsFormDoesNotAcceptNegativeIndices()
    {
        self::expectException(\OutOfBoundsException::class);
        $path = new ViolationPath('children[address].data[street].name');

        $path->mapsForm(-1);
    }
}
