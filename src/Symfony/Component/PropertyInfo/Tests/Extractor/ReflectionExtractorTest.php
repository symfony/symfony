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

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AdderRemoverDummy;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReflectionExtractorTest extends TestCase
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
        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'emptyVar',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'a',
                'DOB',
                'Id',
                '123',
                'self',
                'realParent',
                'xTotals',
                'YT',
                'c',
                'd',
                'e',
                'f',
            ],
            $this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );

        $this->assertNull($this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\NoProperties'));
    }

    public function testGetPropertiesWithCustomPrefixes()
    {
        $customExtractor = new ReflectionExtractor(['add', 'remove'], ['is', 'can']);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'emptyVar',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'c',
                'd',
                'e',
                'f',
            ],
            $customExtractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    public function testGetPropertiesWithNoPrefixes()
    {
        $noPrefixExtractor = new ReflectionExtractor([], [], []);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'emptyVar',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
            ],
            $noPrefixExtractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtractors($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, []));
    }

    public function typesProvider()
    {
        return [
            ['a', null],
            ['b', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['c', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['d', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['e', null],
            ['f', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime'))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
            ['self', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')]],
            ['realParent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
        ];
    }

    /**
     * @dataProvider php7TypesProvider
     * @requires PHP 7.0
     */
    public function testExtractPhp7Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy', $property, []));
    }

    public function php7TypesProvider()
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT)]],
            ['baz', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            ['buz', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy')]],
            ['biz', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'stdClass')]],
            ['donotexist', null],
        ];
    }

    /**
     * @dataProvider php71TypesProvider
     * @requires PHP 7.1
     */
    public function testExtractPhp71Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php71Dummy', $property, []));
    }

    public function php71TypesProvider()
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['buz', [new Type(Type::BUILTIN_TYPE_NULL)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT, true)]],
            ['baz', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            ['donotexist', null],
        ];
    }

    /**
     * @dataProvider getReadableProperties
     */
    public function testIsReadable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, [])
        );
    }

    public function getReadableProperties()
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', true],
            ['b', false],
            ['c', true],
            ['d', true],
            ['e', false],
            ['f', false],
            ['Id', true],
            ['id', true],
            ['Guid', true],
            ['guid', false],
        ];
    }

    /**
     * @dataProvider getWritableProperties
     */
    public function testIsWritable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, [])
        );
    }

    public function getWritableProperties()
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', false],
            ['b', true],
            ['c', false],
            ['d', false],
            ['e', true],
            ['f', true],
            ['Id', false],
            ['Guid', true],
            ['guid', false],
        ];
    }

    public function testSingularize()
    {
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'analyses'));
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'feet'));
        $this->assertEquals(['analyses', 'feet'], $this->extractor->getProperties(AdderRemoverDummy::class));
    }
}
