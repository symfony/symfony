<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Encoder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Encoder\QpContentEncoder;

class QpContentEncoderTest extends TestCase
{
    public function testReplaceLastChar()
    {
        $encoder = new QpContentEncoder();

        $this->assertSame('message=09', $encoder->encodeString('message'.\chr(0x09)));
        $this->assertSame('message=20', $encoder->encodeString('message'.\chr(0x20)));
    }

    #[DataProvider('provideLineBreaks')]
    public function testLineBreaksAreTransformedIntoCrlf(string $expected, string $string)
    {
        $this->assertSame($expected, (new QpContentEncoder())->encodeString($string));
    }

    public static function provideLineBreaks(): iterable
    {
        yield ["a\r\nb", "a\nb"];
        yield ["a\r\nb", "a\rb"];
        yield ["a\r\nb", "a\r\nb"];
        yield ["a=20\r\nb", "a \nb"];
        yield ["a=20\r\nb", "a \rb"];
        yield ["a=20\r\nb", "a \r\nb"];
        yield ["a=09\r\nb", "a\t\nb"];
        yield ["a=09\r\nb", "a\t\r\nb"];
        yield ["a\r\n\r\nb", "a\n\nb"];
        yield ["a\r\n\r\nb", "a\n\rb"];
        yield ["a\r\n\r\nb", "a\r\r\nb"];
        yield ["a =20\r\n b=20\r\n", "a  \n b \n"];
        yield ["\r\n", "\n"];
    }
}
