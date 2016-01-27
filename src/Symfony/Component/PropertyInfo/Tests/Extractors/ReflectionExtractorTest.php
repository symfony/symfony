<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReflectionExtractorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ReflectionExtractor
     */
    private $extractor;

    protected function setUp()
    {
        $this->extractor = new ReflectionExtractor();
    }

    public function testGetProperties()
    {
        $this->assertEquals(
            array(
                'bal',
                'parent',
                'collection',
                'B',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'a',
                'DOB',
                'c',
                'd',
                'e',
                'f',
            ),
            $this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtractors($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, array()));
    }

    public function typesProvider()
    {
        return array(
            array('a', null),
            array('b', array(new Type(Type::BUILTIN_TYPE_OBJECT, true, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy'))),
            array('c', array(new Type(Type::BUILTIN_TYPE_BOOL))),
            array('d', array(new Type(Type::BUILTIN_TYPE_BOOL))),
            array('e', null),
            array('f', array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime')))),
            array('donotexist', null),
        );
    }

    /**
     * @dataProvider php7TypesProvider
     * @requires PHP 7.0
     */
    public function testExtractPhp7Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy', $property, array()));
    }

    public function php7TypesProvider()
    {
        return array(
            array('foo', array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true))),
            array('bar', array(new Type(Type::BUILTIN_TYPE_INT))),
            array('baz', array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING)))),
            array('donotexist', null),
        );
    }

    public function testIsReadable()
    {
        $this->assertFalse($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'bar', array()));
        $this->assertFalse($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'baz', array()));
        $this->assertTrue($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'parent', array()));
        $this->assertTrue($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'a', array()));
        $this->assertFalse($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'b', array()));
        $this->assertTrue($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'c', array()));
        $this->assertTrue($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'd', array()));
        $this->assertFalse($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'e', array()));
        $this->assertFalse($this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'f', array()));
    }

    public function testIsWritable()
    {
        $this->assertFalse($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'bar', array()));
        $this->assertFalse($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'baz', array()));
        $this->assertTrue($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'parent', array()));
        $this->assertFalse($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'a', array()));
        $this->assertTrue($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'b', array()));
        $this->assertFalse($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'c', array()));
        $this->assertFalse($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'd', array()));
        $this->assertTrue($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'e', array()));
        $this->assertTrue($this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', 'f', array()));
    }
}
