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
use Symfony\Component\Serializer\Exception\UnexpectedPropertyException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\NameConverter\SnakeCaseToCamelCaseNameConverter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class SnakeCaseToCamelCaseNameConverterTest extends TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new SnakeCaseToCamelCaseNameConverter();
        $this->assertInstanceOf(NameConverterInterface::class, $attributeMetadata);
    }

    /**
     * @dataProvider Symfony\Component\Serializer\Tests\NameConverter\CamelCaseToSnakeCaseNameConverterTest::attributeProvider
     */
    public function testNormalize($underscored, $camelCased, $useLowerCamelCase)
    {
        $nameConverter = new SnakeCaseToCamelCaseNameConverter(null, $useLowerCamelCase);
        $this->assertEquals($camelCased, $nameConverter->normalize($underscored));
    }

    /**
     * @dataProvider Symfony\Component\Serializer\Tests\NameConverter\CamelCaseToSnakeCaseNameConverterTest::attributeProvider
     */
    public function testDenormalize($underscored, $camelCased, $useLowerCamelCase)
    {
        $nameConverter = new SnakeCaseToCamelCaseNameConverter(null, $useLowerCamelCase);
        $this->assertEquals($underscored, $nameConverter->denormalize($camelCased));
    }

    public function testDenormalizeWithContext()
    {
        $nameConverter = new SnakeCaseToCamelCaseNameConverter(null, true);
        $denormalizedValue = $nameConverter->denormalize('lastName', null, null, [SnakeCaseToCamelCaseNameConverter::REQUIRE_CAMEL_CASE_PROPERTIES => true]);

        $this->assertSame('last_name', $denormalizedValue);
    }

    public function testErrorDenormalizeWithContext()
    {
        $nameConverter = new SnakeCaseToCamelCaseNameConverter(null, true);

        $this->expectException(UnexpectedPropertyException::class);
        $nameConverter->denormalize('last_name', null, null, [SnakeCaseToCamelCaseNameConverter::REQUIRE_CAMEL_CASE_PROPERTIES => true]);
    }
}
