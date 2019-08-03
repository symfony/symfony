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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;

class JsonEncoderTest extends TestCase
{
    private $encoder;
    private $serializer;

    protected function setUp()
    {
        $this->encoder = new JsonEncoder();
        $this->serializer = new Serializer([new CustomNormalizer()], ['json' => new JsonEncoder()]);
    }

    public function testEncodeScalar()
    {
        $obj = new \stdClass();
        $obj->foo = 'foo';

        $expected = '{"foo":"foo"}';

        $this->assertEquals($expected, $this->encoder->encode($obj, 'json'));
    }

    public function testComplexObject()
    {
        $obj = $this->getObject();

        $expected = $this->getJsonSource();

        $this->assertEquals($expected, $this->encoder->encode($obj, 'json'));
    }

    public function testOptions()
    {
        $context = ['json_encode_options' => JSON_NUMERIC_CHECK];

        $arr = [];
        $arr['foo'] = '3';

        $expected = '{"foo":3}';

        $this->assertEquals($expected, $this->serializer->serialize($arr, 'json', $context));

        $arr = [];
        $arr['foo'] = '3';

        $expected = '{"foo":"3"}';

        $this->assertEquals($expected, $this->serializer->serialize($arr, 'json'), 'Context should not be persistent');
    }

    public function testEncodeNotUtf8WithoutPartialOnError()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\UnexpectedValueException');
        $arr = [
            'utf8' => 'Hello World!',
            'notUtf8' => "\xb0\xd0\xb5\xd0",
        ];

        $this->encoder->encode($arr, 'json');
    }

    public function testEncodeNotUtf8WithPartialOnError()
    {
        $context = ['json_encode_options' => JSON_PARTIAL_OUTPUT_ON_ERROR];

        $arr = [
            'utf8' => 'Hello World!',
            'notUtf8' => "\xb0\xd0\xb5\xd0",
        ];

        $result = $this->encoder->encode($arr, 'json', $context);
        $jsonLastError = json_last_error();

        $this->assertSame(JSON_ERROR_UTF8, $jsonLastError);
        $this->assertEquals('{"utf8":"Hello World!","notUtf8":null}', $result);

        $this->assertEquals('0', $this->serializer->serialize(NAN, 'json', $context));
    }

    public function testDecodeFalseString()
    {
        $result = $this->encoder->decode('false', 'json');
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertFalse($result);
    }

    protected function getJsonSource()
    {
        return '{"foo":"foo","bar":["a","b"],"baz":{"key":"val","key2":"val","A B":"bar","item":[{"title":"title1"},{"title":"title2"}],"Barry":{"FooBar":{"Baz":"Ed","@id":1}}},"qux":"1"}';
    }

    protected function getObject()
    {
        $obj = new \stdClass();
        $obj->foo = 'foo';
        $obj->bar = ['a', 'b'];
        $obj->baz = ['key' => 'val', 'key2' => 'val', 'A B' => 'bar', 'item' => [['title' => 'title1'], ['title' => 'title2']], 'Barry' => ['FooBar' => ['Baz' => 'Ed', '@id' => 1]]];
        $obj->qux = '1';

        return $obj;
    }
}
