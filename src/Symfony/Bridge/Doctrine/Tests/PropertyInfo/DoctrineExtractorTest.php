<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\PropertyInfo;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineDummy;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineEnum;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineGeneratedValue;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumInt;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumString;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineExtractorTest extends TestCase
{
    private function createExtractor()
    {
        $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.\DIRECTORY_SEPARATOR.'Fixtures'], true);
        $entityManager = EntityManager::create(['driver' => 'pdo_sqlite'], $config);

        if (!DBALType::hasType('foo')) {
            DBALType::addType('foo', 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineFooType');
            $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('custom_foo', 'foo');
        }

        return new DoctrineExtractor($entityManager);
    }

    public function testGetProperties()
    {
        // Fields
        $expected = [
            'id',
            'guid',
            'time',
            'timeImmutable',
            'dateInterval',
            'jsonArray',
            'simpleArray',
            'float',
            'decimal',
            'bool',
            'binary',
            'customFoo',
            'bigint',
            'json',
        ];

        // Associations
        $expected = array_merge($expected, [
            'foo',
            'bar',
            'indexedRguid',
            'indexedBar',
            'indexedFoo',
            'indexedBaz',
            'indexedByDt',
            'indexedByCustomType',
            'indexedBuz',
            'dummyGeneratedValueList',
        ]);

        $this->assertEquals(
            $expected,
            $this->createExtractor()->getProperties(DoctrineDummy::class)
        );
    }

    public function testTestGetPropertiesWithEmbedded()
    {
        $this->assertEquals(
            [
                'id',
                'embedded',
            ],
            $this->createExtractor()->getProperties('Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineWithEmbedded')
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtract($property, array $type = null)
    {
        $this->assertEquals($type, $this->createExtractor()->getTypes(DoctrineDummy::class, $property, []));
    }

    public function testExtractWithEmbedded()
    {
        $expectedTypes = [new Type(
            Type::BUILTIN_TYPE_OBJECT,
            false,
            'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineEmbeddable'
        )];

        $actualTypes = $this->createExtractor()->getTypes(
            'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineWithEmbedded',
            'embedded',
            []
        );

        $this->assertEquals($expectedTypes, $actualTypes);
    }

    public function testExtractEnum()
    {
        if (!property_exists(Column::class, 'enumType')) {
            $this->markTestSkipped('The "enumType" requires doctrine/orm 2.11.');
        }
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_OBJECT, false, EnumString::class)], $this->createExtractor()->getTypes(DoctrineEnum::class, 'enumString', []));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_OBJECT, false, EnumInt::class)], $this->createExtractor()->getTypes(DoctrineEnum::class, 'enumInt', []));
        $this->assertNull($this->createExtractor()->getTypes(DoctrineEnum::class, 'enumStringArray', []));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, EnumInt::class))], $this->createExtractor()->getTypes(DoctrineEnum::class, 'enumIntArray', []));
        $this->assertNull($this->createExtractor()->getTypes(DoctrineEnum::class, 'enumCustom', []));
    }

    public static function typesProvider()
    {
        $provider = [
            ['id', [new Type(Type::BUILTIN_TYPE_INT)]],
            ['guid', [new Type(Type::BUILTIN_TYPE_STRING)]],
            ['bigint', [new Type(Type::BUILTIN_TYPE_STRING)]],
            ['time', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime')]],
            ['timeImmutable', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['dateInterval', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateInterval')]],
            ['float', [new Type(Type::BUILTIN_TYPE_FLOAT)]],
            ['decimal', [new Type(Type::BUILTIN_TYPE_STRING)]],
            ['bool', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['binary', [new Type(Type::BUILTIN_TYPE_RESOURCE)]],
            ['jsonArray', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)]],
            ['foo', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')]],
            ['bar', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_INT),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            )]],
            ['indexedRguid', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_STRING),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            )]],
            ['indexedBar', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_STRING),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            )]],
            ['indexedFoo', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_STRING),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            )]],
            ['indexedBaz', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                Collection::class,
                true,
                new Type(Type::BUILTIN_TYPE_INT),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, DoctrineRelation::class)
            )]],
            ['simpleArray', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            ['customFoo', null],
            ['notMapped', null],
            ['indexedByDt', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                Collection::class,
                true,
                new Type(Type::BUILTIN_TYPE_OBJECT),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, DoctrineRelation::class)
            )]],
            ['indexedByCustomType', null],
            ['indexedBuz', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                Collection::class,
                true,
                new Type(Type::BUILTIN_TYPE_STRING),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, DoctrineRelation::class)
            )]],
            ['dummyGeneratedValueList', [new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_INT),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, DoctrineRelation::class)
            )]],
            ['json', null],
        ];

        return $provider;
    }

    public function testGetPropertiesCatchException()
    {
        $this->assertNull($this->createExtractor()->getProperties('Not\Exist'));
    }

    public function testGetTypesCatchException()
    {
        $this->assertNull($this->createExtractor()->getTypes('Not\Exist', 'baz'));
    }

    public function testGeneratedValueNotWritable()
    {
        $extractor = $this->createExtractor();
        $this->assertFalse($extractor->isWritable(DoctrineGeneratedValue::class, 'id'));
        $this->assertNull($extractor->isReadable(DoctrineGeneratedValue::class, 'id'));
        $this->assertNull($extractor->isWritable(DoctrineGeneratedValue::class, 'foo'));
        $this->assertNull($extractor->isReadable(DoctrineGeneratedValue::class, 'foo'));
    }
}
