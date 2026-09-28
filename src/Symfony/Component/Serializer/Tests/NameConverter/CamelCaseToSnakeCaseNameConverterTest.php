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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\UnexpectedPropertyException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class CamelCaseToSnakeCaseNameConverterTest extends TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new CamelCaseToSnakeCaseNameConverter();
        $this->assertInstanceOf(NameConverterInterface::class, $attributeMetadata);
    }

    #[DataProvider('attributeProvider')]
    public function testNormalize($underscored, $camelCased, $useLowerCamelCase)
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(null, $useLowerCamelCase);
        $this->assertEquals($nameConverter->normalize($camelCased), $underscored);
    }

    #[DataProvider('attributeProvider')]
    public function testDenormalize($underscored, $camelCased, $useLowerCamelCase)
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(null, $useLowerCamelCase);
        $this->assertEquals($nameConverter->denormalize($underscored), $camelCased);
    }

    public static function attributeProvider()
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

    public function testNormalizeOnlyTheGivenAttributes()
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(['fooBar']);
        $otherNameConverter = new CamelCaseToSnakeCaseNameConverter(['barBaz']);

        $this->assertSame('foo_bar', $nameConverter->normalize('fooBar'));
        $this->assertSame('barBaz', $nameConverter->normalize('barBaz'));
        $this->assertSame('fooBar', $otherNameConverter->normalize('fooBar'));
        $this->assertSame('bar_baz', $otherNameConverter->normalize('barBaz'));

        $this->assertSame('foo_bar', $nameConverter->normalize('fooBar'));
        $this->assertSame('barBaz', $nameConverter->normalize('barBaz'));
    }

    public function testNormalizeKeepsConvertingAfterManyNames()
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();

        for ($i = 0; $i < 2000; ++$i) {
            $this->assertSame('name'.$i.'_foo', $nameConverter->normalize('name'.$i.'Foo'));
        }

        $this->assertLessThanOrEqual(1001, \count((new \ReflectionProperty($nameConverter, 'normalizeCache'))->getValue($nameConverter)));
        $this->assertSame('foo_bar', $nameConverter->normalize('fooBar'));
        $this->assertSame('name1_foo', $nameConverter->normalize('name1Foo'));
    }

    public function testDenormalizeWithContext()
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(null, true);
        $denormalizedValue = $nameConverter->denormalize('last_name', null, null, [CamelCaseToSnakeCaseNameConverter::REQUIRE_SNAKE_CASE_PROPERTIES => true]);

        $this->assertSame('lastName', $denormalizedValue);
    }

    public function testErrorDenormalizeWithContext()
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter(null, true);

        $this->expectException(UnexpectedPropertyException::class);
        $nameConverter->denormalize('lastName', null, null, [CamelCaseToSnakeCaseNameConverter::REQUIRE_SNAKE_CASE_PROPERTIES => true]);
    }
}
