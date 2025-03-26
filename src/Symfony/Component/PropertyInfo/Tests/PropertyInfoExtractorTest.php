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

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\TypeInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoExtractorTest extends AbstractPropertyInfoExtractorTest
{
    /**
     * @group legacy
     *
     * @dataProvider provideNestedExtractorWithoutGetTypeImplementationData
     */
    public function testNestedExtractorWithoutGetTypeImplementation(string $property, ?Type $expectedType)
    {
        $propertyInfoExtractor = new PropertyInfoExtractor([], [new class implements PropertyTypeExtractorInterface {
            private PropertyTypeExtractorInterface $propertyTypeExtractor;

            public function __construct()
            {
                $this->propertyTypeExtractor = new PhpDocExtractor();
            }

            public function getTypes(string $class, string $property, array $context = []): ?array
            {
                return $this->propertyTypeExtractor->getTypes($class, $property, $context);
            }
        }]);

        if (null === $expectedType) {
            $this->assertNull($propertyInfoExtractor->getType(Dummy::class, $property));
        } else {
            $this->assertEquals($expectedType, $propertyInfoExtractor->getType(Dummy::class, $property));
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
