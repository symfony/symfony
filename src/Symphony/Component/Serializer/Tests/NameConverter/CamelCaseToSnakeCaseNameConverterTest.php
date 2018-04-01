<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\NameConverter;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CamelCaseToSnakeCaseNameConverterTest extends TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new CamelCaseToSnakeCaseNameConverter();
        $this->assertInstanceOf('Symphony\Component\Serializer\NameConverter\NameConverterInterface', $attributeMetadata);
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testNormalize($underscored, $camelCased, $useLowerCamelCase)
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(null, $useLowerCamelCase);
        $this->assertEquals($nameConverter->normalize($camelCased), $underscored);
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testDenormalize($underscored, $camelCased, $useLowerCamelCase)
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(null, $useLowerCamelCase);
        $this->assertEquals($nameConverter->denormalize($underscored), $camelCased);
    }

    public function attributeProvider()
    {
        return array(
            array('coop_tilleuls', 'coopTilleuls', true),
            array('_kevin_dunglas', '_kevinDunglas', true),
            array('this_is_a_test', 'thisIsATest', true),
            array('coop_tilleuls', 'CoopTilleuls', false),
            array('_kevin_dunglas', '_kevinDunglas', false),
            array('this_is_a_test', 'ThisIsATest', false),
        );
    }
}
