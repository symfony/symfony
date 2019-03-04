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

class JsonDecodeTest extends TestCase
{
    /** @var \Symfony\Component\Serializer\Encoder\JsonDecode */
    private $decode;

    protected function setUp()
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

    public function decodeProvider()
    {
        $stdClass = new \stdClass();
        $stdClass->foo = 'bar';

        $assoc = ['foo' => 'bar'];

        return array(
            ['{"foo": "bar"}', $stdClass, []],
            ['{"foo": "bar"}', $assoc, ['json_decode_associative' => true]],
            array('{"baz": {"foo": "bar"}}', $stdClass, array(JsonEncoder::JSON_PROPERTY_PATH => 'baz')),
            array('{"baz": {"foo": "bar"}}', null, array(JsonEncoder::JSON_PROPERTY_PATH => 'baz.inner')),
            array('{"baz": {"foo": "bar"}}', $assoc, array(JsonEncoder::JSON_PROPERTY_PATH => '[baz]', 'json_decode_associative' => true)),
            array('{"baz": {"foo": "bar"}}', $assoc, array(JsonEncoder::JSON_PROPERTY_PATH => '[baz]', 'json_decode_associative' => true)),
            array('{"baz": {"foo": "bar", "inner": {"key": "value"}}}', array('key' => 'value'), array(JsonEncoder::JSON_PROPERTY_PATH => '[baz][inner]', 'json_decode_associative' => true)),
        );
    }

    /**
     * @dataProvider decodeProviderException
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDecodeWithException($value)
    {
        $this->decode->decode($value, JsonEncoder::FORMAT);
    }

    public function decodeProviderException()
    {
        return [
            ["{'foo': 'bar'}"],
            ['kaboom!'],
        ];
    }
}
