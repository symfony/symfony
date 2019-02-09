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

        $this->assertEquals([new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_INT))], static::$container->get('property_info')->getTypes('Symfony\Bundle\FrameworkBundle\Tests\Functional\Dummy', 'codes'));
    }

    /**
     * @dataProvider constructorOverridesPropertyTypeProvider
     */
    public function testConstructorOverridesPropertyType($property, array $type = null)
    {
        static::bootKernel(['test_case' => 'Serializer']);
        $extractor = static::$container->get('property_info');
        $this->assertEquals($type, $extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy', $property));
    }

    public function constructorOverridesPropertyTypeProvider()
    {
        return [
            ['timezone', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeZone')]],
            ['date', [new Type(Type::BUILTIN_TYPE_INT)]],
            ['dateObject', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeInterface')]],
            ['dateTime', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime')]],
            ['ddd', null],
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
