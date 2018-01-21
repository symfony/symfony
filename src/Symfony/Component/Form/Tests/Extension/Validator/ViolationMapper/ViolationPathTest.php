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
        return array(
            array('children[address]', array(
                array('address', true, true),
            )),
            array('children[address].children[street]', array(
                array('address', true, true),
                array('street', true, true),
            )),
            array('children[address][street]', array(
                array('address', true, true),
                array('street', true, true),
            ), 'children[address].children[street]'),
            array('children[address].data', array(
                array('address', true, true),
            ), 'children[address]'),
            array('children[address].data.street', array(
                array('address', true, true),
                array('street', false, false),
            )),
            array('children[address].data[street]', array(
                array('address', true, true),
                array('street', false, true),
            )),
            array('children[address].children[street].data.name', array(
                array('address', true, true),
                array('street', true, true),
                array('name', false, false),
            )),
            array('children[address].children[street].data[name]', array(
                array('address', true, true),
                array('street', true, true),
                array('name', false, true),
            )),
            array('data.address', array(
                array('address', false, false),
            )),
            array('data[address]', array(
                array('address', false, true),
            )),
            array('data.address.street', array(
                array('address', false, false),
                array('street', false, false),
            )),
            array('data[address].street', array(
                array('address', false, true),
                array('street', false, false),
            )),
            array('data.address[street]', array(
                array('address', false, false),
                array('street', false, true),
            )),
            array('data[address][street]', array(
                array('address', false, true),
                array('street', false, true),
            )),
            // A few invalid examples
            array('data', array(), ''),
            array('children', array(), ''),
            array('children.address', array(), ''),
            array('children.address[street]', array(), ''),
        );
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

        $this->assertSame($slicedPath, $path->__toString());
        $this->assertCount(count($entries), $path->getElements());
        $this->assertSame(count($entries), $path->getLength());

        foreach ($entries as $index => $entry) {
            $this->assertEquals($entry[0], $path->getElement($index));
            $this->assertSame($entry[1], $path->mapsForm($index));
            $this->assertSame($entry[2], $path->isIndex($index));
            $this->assertSame(!$entry[2], $path->isProperty($index));
        }
    }

    public function provideParents()
    {
        return array(
            array('children[address]', null),
            array('children[address].children[street]', 'children[address]'),
            array('children[address].data.street', 'children[address]'),
            array('children[address].data[street]', 'children[address]'),
            array('data.address', null),
            array('data.address.street', 'data.address'),
            array('data.address[street]', 'data.address'),
            array('data[address].street', 'data[address]'),
            array('data[address][street]', 'data[address]'),
        );
    }

    /**
     * @dataProvider provideParents
     */
    public function testGetParent($violationPath, $parentPath)
    {
        $path = new ViolationPath($violationPath);
        $parent = null === $parentPath ? null : new ViolationPath($parentPath);

        $this->assertEquals($parent, $path->getParent());
    }

    public function testGetElement()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertEquals('street', $path->getElement(1));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetElementDoesNotAcceptInvalidIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->getElement(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetElementDoesNotAcceptNegativeIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->getElement(-1);
    }

    public function testIsProperty()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertFalse($path->isProperty(1));
        $this->assertTrue($path->isProperty(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsPropertyDoesNotAcceptInvalidIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->isProperty(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsPropertyDoesNotAcceptNegativeIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->isProperty(-1);
    }

    public function testIsIndex()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertTrue($path->isIndex(1));
        $this->assertFalse($path->isIndex(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsIndexDoesNotAcceptInvalidIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->isIndex(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsIndexDoesNotAcceptNegativeIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->isIndex(-1);
    }

    public function testMapsForm()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $this->assertTrue($path->mapsForm(0));
        $this->assertFalse($path->mapsForm(1));
        $this->assertFalse($path->mapsForm(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testMapsFormDoesNotAcceptInvalidIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->mapsForm(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testMapsFormDoesNotAcceptNegativeIndices()
    {
        $path = new ViolationPath('children[address].data[street].name');

        $path->mapsForm(-1);
    }
}
