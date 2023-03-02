<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class JsonDecodeTest extends TestCase
{
    /** @var \Symfony\Component\Serializer\Encoder\JsonDecode */
    private $decode;

    protected function setUp(): void
    {
        $this->decode = new JsonDecode();
    }

    public function testSupportsDecoding()
    {
        $this->assertTrue($this->decode->supportsDecoding(JsonEncoder::FORMAT));
        $this->assertFalse($this->decode->supportsDecoding('foobar'));
    }

    /**
     * @dataProvider decodeProvider
     */
    public function testDecode($toDecode, $expected, $context)
    {
        $this->assertEquals(
            $expected,
            $this->decode->decode($toDecode, JsonEncoder::FORMAT, $context)
        );
    }

    public static function decodeProvider()
    {
        $stdClass = new \stdClass();
        $stdClass->foo = 'bar';

        $assoc = ['foo' => 'bar'];

        return [
            ['{"foo": "bar"}', $stdClass, []],
            ['{"foo": "bar"}', $assoc, ['json_decode_associative' => true]],
        ];
    }

    /**
     * @dataProvider decodeProviderException
     */
    public function testDecodeWithException($value)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->decode->decode($value, JsonEncoder::FORMAT);
    }

    public static function decodeProviderException()
    {
        return [
            ["{'foo': 'bar'}"],
            ['kaboom!'],
        ];
    }
}
