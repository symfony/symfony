<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidStreamException;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\JsonStreamer\Read\Decoder;

class DecoderTest extends TestCase
{
    public function testDecode()
    {
        $this->assertDecoded('foo', '"foo"');
    }

    public function testDecodeSubset()
    {
        $this->assertDecoded('bar', '["foo","bar","baz"]', 7, 5);
    }

    public function testDecodeThrowOnInvalidJsonString()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('JSON is not valid: Syntax error');

        Decoder::decodeString('foo"');
    }

    public function testDecodeThrowOnInvalidJsonStream()
    {
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('JSON is not valid: Syntax error');

        $resource = fopen('php://temp', 'w');
        fwrite($resource, 'foo"');
        rewind($resource);

        Decoder::decodeStream($resource);
    }

    #[DataProvider('hasDuplicateKeysDataProvider')]
    public function testHasDuplicateKeys(bool $hasDuplicateKeys, string $encoded)
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, '  '.$encoded);

        $this->assertSame($hasDuplicateKeys, Decoder::hasDuplicateKeys($resource, 2, \strlen($encoded), json_decode($encoded)));
    }

    public static function hasDuplicateKeysDataProvider(): iterable
    {
        yield [false, '1'];
        yield [false, '[]'];
        yield [false, '{}'];
        yield [false, '{"":1}'];
        yield [false, '{"id":1,"name":"foo"}'];
        yield [false, '{ "id" : 1 , "name" : "a:b" }'];
        yield [false, '{"a":"x",":b":1}'];
        yield [false, '{"a":"\\":","b\\"":":"}'];
        yield [false, '[{"id":1},{"id":1}]'];
        yield [false, '{"a":{"id":1},"b":[{"id":1,"name":{"id":1}}]}'];
        yield [false, '{"a\\\\":1,"b":"\\\\"}'];
        yield [false, '{"1":1,"01":2}'];
        yield [true, '{"id":1,"id":2}'];
        yield [true, '{"id":1,"\\u0069d":2}'];
        yield [true, '{"1":1,"1":2}'];
        yield [true, '{"1":1,"\\u0031":2}'];
        yield [true, '{"":1,"":2}'];
        yield [true, '{"a":{"id":1,"id":2}}'];
        yield [true, '[[{"id":1,"id":2}]]'];
        yield [true, '{"a":{"id":1},"a":{"id":2}}'];
    }

    public function testHasDuplicateKeysOnRegexFailure()
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, '{"id":1}');

        $backtrackLimit = ini_set('pcre.backtrack_limit', '1');

        try {
            $this->assertTrue(Decoder::hasDuplicateKeys($resource, 0, 8, json_decode('{"id":1}')));
        } finally {
            ini_set('pcre.backtrack_limit', $backtrackLimit);
        }
    }

    public function testIsNull()
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, '[null,"null",{"n":1},[null]]');
        rewind($resource);

        $this->assertTrue(Decoder::isNull($resource, 1));
        $this->assertFalse(Decoder::isNull($resource, 6));
        $this->assertFalse(Decoder::isNull($resource, 13));
        $this->assertFalse(Decoder::isNull($resource, 21));
    }

    private function assertDecoded(mixed $decoded, string $encoded, int $offset = 0, ?int $length = null): void
    {
        if (0 === $offset && null === $length) {
            $this->assertEquals($decoded, Decoder::decodeString($encoded));
        }

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $encoded);
        rewind($resource);

        $this->assertEquals($decoded, Decoder::decodeStream($resource, $offset, $length));
    }
}
