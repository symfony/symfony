<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Marshaller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;

class DefaultMarshallerTest extends TestCase
{
    public function testSerialize()
    {
        $marshaller = new DefaultMarshaller();
        $values = [
            'a' => 123,
            'b' => function () {},
        ];

        $expected = ['a' => \extension_loaded('igbinary') && \PHP_VERSION_ID !== 70400 ? igbinary_serialize(123) : serialize(123)];
        $this->assertSame($expected, $marshaller->marshall($values, $failed));
        $this->assertSame(['b'], $failed);
    }

    public function testNativeUnserialize()
    {
        $marshaller = new DefaultMarshaller();
        $this->assertNull($marshaller->unmarshall(serialize(null)));
        $this->assertFalse($marshaller->unmarshall(serialize(false)));
        $this->assertSame('', $marshaller->unmarshall(serialize('')));
        $this->assertSame(0, $marshaller->unmarshall(serialize(0)));
    }

    /**
     * @requires extension igbinary
     */
    public function testIgbinaryUnserialize()
    {
        if (\PHP_VERSION_ID === 70400) {
            $this->markTestSkipped('igbinary is not compatible with PHP 7.4.0.');
        }

        $marshaller = new DefaultMarshaller();
        $this->assertNull($marshaller->unmarshall(igbinary_serialize(null)));
        $this->assertFalse($marshaller->unmarshall(igbinary_serialize(false)));
        $this->assertSame('', $marshaller->unmarshall(igbinary_serialize('')));
        $this->assertSame(0, $marshaller->unmarshall(igbinary_serialize(0)));
    }

    public function testNativeUnserializeNotFoundClass()
    {
        $this->expectException('DomainException');
        $this->expectExceptionMessage('Class not found: NotExistingClass');
        $marshaller = new DefaultMarshaller();
        $marshaller->unmarshall('O:16:"NotExistingClass":0:{}');
    }

    /**
     * @requires extension igbinary
     */
    public function testIgbinaryUnserializeNotFoundClass()
    {
        if (\PHP_VERSION_ID === 70400) {
            $this->markTestSkipped('igbinary is not compatible with PHP 7.4.0.');
        }

        $this->expectException('DomainException');
        $this->expectExceptionMessage('Class not found: NotExistingClass');
        $marshaller = new DefaultMarshaller();
        $marshaller->unmarshall(rawurldecode('%00%00%00%02%17%10NotExistingClass%14%00'));
    }

    public function testNativeUnserializeInvalid()
    {
        $this->expectException('DomainException');
        $this->expectExceptionMessage('unserialize(): Error at offset 0 of 3 bytes');
        $marshaller = new DefaultMarshaller();
        set_error_handler(function () { return false; });
        try {
            @$marshaller->unmarshall(':::');
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @requires extension igbinary
     */
    public function testIgbinaryUnserializeInvalid()
    {
        if (\PHP_VERSION_ID === 70400) {
            $this->markTestSkipped('igbinary is not compatible with PHP 7.4.0');
        }

        $this->expectException('DomainException');
        $this->expectExceptionMessage('igbinary_unserialize_zval: unknown type \'61\', position 5');
        $marshaller = new DefaultMarshaller();
        set_error_handler(function () { return false; });
        try {
            @$marshaller->unmarshall(rawurldecode('%00%00%00%02abc'));
        } finally {
            restore_error_handler();
        }
    }
}
