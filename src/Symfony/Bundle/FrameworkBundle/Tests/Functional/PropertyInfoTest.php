<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyInfo\Type;

class PropertyInfoTest extends WebTestCase
{
    public function testPhpDocPriority()
    {
        static::bootKernel(['test_case' => 'Serializer']);
        $container = static::$kernel->getContainer();

        $this->assertEquals([new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_INT))], $container->get('test.property_info')->getTypes('Symfony\Bundle\FrameworkBundle\Tests\Functional\Dummy', 'codes'));
    }

    /**
     * @dataProvider constructorOverridesPropertyTypeProvider
     */
    public function testConstructorOverridesPropertyType(ContainerInterface $container, $property, array $type = null)
    {
        $extractor = $container->get('test.property_info');
        $this->assertEquals($type, $extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy', $property));
    }

    public function constructorOverridesPropertyTypeProvider()
    {
        static::bootKernel(['test_case' => 'Serializer']);
        $c = static::$kernel->getContainer();

        return [
            [$c, 'timezone', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeZone')]],
            [$c, 'date', [new Type(Type::BUILTIN_TYPE_INT)]],
            [$c, 'dateObject', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeInterface')]],
            [$c, 'dateTime', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime')]],
            [$c, 'ddd', null],
        ];
    }
}

class Dummy
{
    /**
     * @param int[] $codes
     */
    public function setCodes(array $codes)
    {
    }
}
