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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineDummy210;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineExtractorTest extends TestCase
{
    /**
     * @var DoctrineExtractor
     */
    private $extractor;

    protected function setUp()
    {
        $config = Setup::createAnnotationMetadataConfiguration([__DIR__.\DIRECTORY_SEPARATOR.'Fixtures'], true);
        $entityManager = EntityManager::create(['driver' => 'pdo_sqlite'], $config);

        if (!DBALType::hasType('foo')) {
            DBALType::addType('foo', 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineFooType');
            $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('custom_foo', 'foo');
        }

        $this->extractor = new DoctrineExtractor($entityManager->getMetadataFactory());
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
        ];

        if (class_exists(Types::class)) {
            $expected[] = 'json';
        }

        // Associations
        $expected = array_merge($expected, [
            'foo',
            'bar',
            'indexedBar',
            'indexedFoo',
            'indexedByDt',
            'indexedByCustomType',
        ]);

        $this->assertEquals(
            $expected,
            $this->extractor->getProperties(!class_exists(Types::class) ? 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineDummy' : DoctrineDummy210::class)
        );
    }

    public function testGetPropertiesWithEmbedded()
    {
        if (!class_exists('Doctrine\ORM\Mapping\Embedded')) {
            $this->markTestSkipped('@Embedded is not available in Doctrine ORM lower than 2.5.');
        }

        $this->assertEquals(
            [
                'id',
                'embedded',
            ],
            $this->extractor->getProperties('Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineWithEmbedded')
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtract($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes(!class_exists(Types::class) ? 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineDummy' : DoctrineDummy210::class, $property, []));
    }

    public function testExtractWithEmbedded()
    {
        if (!class_exists('Doctrine\ORM\Mapping\Embedded')) {
            $this->markTestSkipped('@Embedded is not available in Doctrine ORM lower than 2.5.');
        }

        $expectedTypes = [new Type(
            Type::BUILTIN_TYPE_OBJECT,
            false,
            'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineEmbeddable'
        )];

        $actualTypes = $this->extractor->getTypes(
            'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineWithEmbedded',
            'embedded',
            []
        );

        $this->assertEquals($expectedTypes, $actualTypes);
    }

    public function typesProvider()
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
        ];

        if (class_exists(Types::class)) {
            $provider[] = ['json', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)]];
        }

        return $provider;
    }

    public function testGetPropertiesCatchException()
    {
        $this->assertNull($this->extractor->getProperties('Not\Exist'));
    }

    public function testGetTypesCatchException()
    {
        $this->assertNull($this->extractor->getTypes('Not\Exist', 'baz'));
    }
}
