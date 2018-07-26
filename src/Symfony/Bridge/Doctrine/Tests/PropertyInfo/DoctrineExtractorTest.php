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

use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
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
        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__.\DIRECTORY_SEPARATOR.'Fixtures'), true);
        $entityManager = EntityManager::create(array('driver' => 'pdo_sqlite'), $config);

        if (!DBALType::hasType('foo')) {
            DBALType::addType('foo', 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineFooType');
            $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('custom_foo', 'foo');
        }

        $this->extractor = new DoctrineExtractor($entityManager->getMetadataFactory());
    }

    public function testGetProperties()
    {
        $this->assertEquals(
             array(
                'id',
                'guid',
                'time',
                'json',
                'simpleArray',
                'float',
                'decimal',
                'bool',
                'binary',
                'customFoo',
                'bigint',
                'foo',
                'bar',
                'indexedBar',
                'indexedFoo',
            ),
            $this->extractor->getProperties('Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineDummy')
        );
    }

    public function testGetPropertiesWithEmbedded()
    {
        if (!class_exists('Doctrine\ORM\Mapping\Embedded')) {
            $this->markTestSkipped('@Embedded is not available in Doctrine ORM lower than 2.5.');
        }

        $this->assertEquals(
            array(
                'id',
                'embedded',
            ),
            $this->extractor->getProperties('Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineWithEmbedded')
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtract($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineDummy', $property, array()));
    }

    public function testExtractWithEmbedded()
    {
        if (!class_exists('Doctrine\ORM\Mapping\Embedded')) {
            $this->markTestSkipped('@Embedded is not available in Doctrine ORM lower than 2.5.');
        }

        $expectedTypes = array(new Type(
            Type::BUILTIN_TYPE_OBJECT,
            false,
            'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineEmbeddable'
        ));

        $actualTypes = $this->extractor->getTypes(
            'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineWithEmbedded',
            'embedded',
            array()
        );

        $this->assertEquals($expectedTypes, $actualTypes);
    }

    public function typesProvider()
    {
        return array(
            array('id', array(new Type(Type::BUILTIN_TYPE_INT))),
            array('guid', array(new Type(Type::BUILTIN_TYPE_STRING))),
            array('bigint', array(new Type(Type::BUILTIN_TYPE_STRING))),
            array('float', array(new Type(Type::BUILTIN_TYPE_FLOAT))),
            array('decimal', array(new Type(Type::BUILTIN_TYPE_STRING))),
            array('bool', array(new Type(Type::BUILTIN_TYPE_BOOL))),
            array('binary', array(new Type(Type::BUILTIN_TYPE_RESOURCE))),
            array('json', array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true))),
            array('foo', array(new Type(Type::BUILTIN_TYPE_OBJECT, true, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation'))),
            array('bar', array(new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_INT),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            ))),
            array('indexedBar', array(new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_STRING),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            ))),
            array('indexedFoo', array(new Type(
                Type::BUILTIN_TYPE_OBJECT,
                false,
                'Doctrine\Common\Collections\Collection',
                true,
                new Type(Type::BUILTIN_TYPE_STRING),
                new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\DoctrineRelation')
            ))),
            array('simpleArray', array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING)))),
            array('customFoo', null),
            array('notMapped', null),
        );
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
