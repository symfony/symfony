<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\NameConverter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CamelCaseToSnakeCaseNameConverterTest extends TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new CamelCaseToSnakeCaseNameConverter();
        $this->assertInstanceOf('Symfony\Component\Serializer\NameConverter\NameConverterInterface', $attributeMetadata);
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
        return [
            ['coop_tilleuls', 'coopTilleuls', true],
            ['_kevin_dunglas', '_kevinDunglas', true],
            ['this_is_a_test', 'thisIsATest', true],
            ['coop_tilleuls', 'CoopTilleuls', false],
            ['_kevin_dunglas', '_kevinDunglas', false],
            ['this_is_a_test', 'ThisIsATest', false],
        ];
    }
}
