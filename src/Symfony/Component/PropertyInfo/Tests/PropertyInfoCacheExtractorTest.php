<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\TypeInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoCacheExtractorTest extends AbstractPropertyInfoExtractorTest
{
    use ExpectDeprecationTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyInfo = new PropertyInfoCacheExtractor($this->propertyInfo, new ArrayAdapter());
    }

    public function testGetShortDescription()
    {
        parent::testGetShortDescription();
        parent::testGetShortDescription();
    }

    public function testGetLongDescription()
    {
        parent::testGetLongDescription();
        parent::testGetLongDescription();
    }

    public function testGetType()
    {
        parent::testGetType();
        parent::testGetType();
    }

    /**
     * @group legacy
     */
    public function testGetTypes()
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor::getType()" instead.');

        parent::testGetTypes();
        parent::testGetTypes();
    }

    public function testIsReadable()
    {
        parent::testIsReadable();
        parent::testIsReadable();
    }

    public function testIsWritable()
    {
        parent::testIsWritable();
        parent::testIsWritable();
    }

    public function testGetProperties()
    {
        parent::testGetProperties();
        parent::testGetProperties();
    }

    public function testIsInitializable()
    {
        parent::testIsInitializable();
        parent::testIsInitializable();
    }

    /**
     * @group legacy
     *
     * @dataProvider provideNestedExtractorWithoutGetTypeImplementationData
     */
    public function testNestedExtractorWithoutGetTypeImplementation(string $property, ?Type $expectedType)
    {
        $propertyInfoCacheExtractor = new PropertyInfoCacheExtractor(new class implements PropertyInfoExtractorInterface {
            private PropertyTypeExtractorInterface $propertyTypeExtractor;

            public function __construct()
            {
                $this->propertyTypeExtractor = new PhpDocExtractor();
            }

            public function getTypes(string $class, string $property, array $context = []): ?array
            {
                return $this->propertyTypeExtractor->getTypes($class, $property, $context);
            }

            public function isReadable(string $class, string $property, array $context = []): ?bool
            {
                return null;
            }

            public function isWritable(string $class, string $property, array $context = []): ?bool
            {
                return null;
            }

            public function getShortDescription(string $class, string $property, array $context = []): ?string
            {
                return null;
            }

            public function getLongDescription(string $class, string $property, array $context = []): ?string
            {
                return null;
            }

            public function getProperties(string $class, array $context = []): ?array
            {
                return null;
            }
        }, new ArrayAdapter());

        if (null === $expectedType) {
            $this->assertNull($propertyInfoCacheExtractor->getType(Dummy::class, $property));
        } else {
            $this->assertEquals($expectedType, $propertyInfoCacheExtractor->getType(Dummy::class, $property));
        }
    }

    public static function provideNestedExtractorWithoutGetTypeImplementationData()
    {
        yield ['bar', Type::string()];
        yield ['baz', Type::int()];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class), Type::int())];
        yield ['nestedCollection', Type::array(Type::array(Type::string(), Type::int()), Type::int())];
        yield ['mixedCollection', Type::array()];
        yield ['B', Type::object(ParentDummy::class)];
        yield ['Id', Type::int()];
        yield ['Guid', Type::string()];
        yield ['g', Type::nullable(Type::array())];
        yield ['h', Type::nullable(Type::string())];
        yield ['i', Type::nullable(Type::union(Type::string(), Type::int()))];
        yield ['j', Type::nullable(Type::object(\DateTimeImmutable::class))];
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::array(Type::int(), Type::int()))];
        yield ['nonNullableCollectionOfNullableElements', Type::array(Type::nullable(Type::int()), Type::int())];
        yield ['nullableCollectionOfMultipleNonNullableElementTypes', Type::nullable(Type::array(Type::union(Type::int(), Type::string()), Type::int()))];
        yield ['xTotals', Type::array()];
        yield ['YT', Type::string()];
        yield ['emptyVar', null];
        yield ['iteratorCollection', Type::collection(Type::object(\Iterator::class), Type::string(), Type::union(Type::string(), Type::int()))];
        yield ['iteratorCollectionWithKey', Type::collection(Type::object(\Iterator::class), Type::string(), Type::int())];
        yield ['nestedIterators', Type::collection(Type::object(\Iterator::class), Type::collection(Type::object(\Iterator::class), Type::string(), Type::int()), Type::int())];
        yield ['arrayWithKeys', Type::array(Type::string(), Type::string())];
        yield ['arrayWithKeysAndComplexValue', Type::array(Type::nullable(Type::array(Type::nullable(Type::string()), Type::int())), Type::string())];
        yield ['arrayOfMixed', Type::array(Type::mixed(), Type::string())];
        yield ['noDocBlock', null];
        yield ['listOfStrings', Type::array(Type::string(), Type::int())];
        yield ['parentAnnotation', Type::object(ParentDummy::class)];
    }
}
