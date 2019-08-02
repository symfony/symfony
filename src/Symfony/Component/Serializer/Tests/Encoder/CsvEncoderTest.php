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
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CsvEncoderTest extends TestCase
{
    /**
     * @var CsvEncoder
     */
    private $encoder;

    protected function setUp()
    {
        $this->encoder = new CsvEncoder();
    }

    public function testTrueFalseValues()
    {
        $data = [
            'string' => 'foo',
            'int' => 2,
            'false' => false,
            'true' => true,
        ];

        // Check that true and false are appropriately handled
        $this->assertEquals(<<<'CSV'
string,int,false,true
foo,2,0,1

CSV
    , $this->encoder->encode($data, 'csv'));
    }

    public function testSupportEncoding()
    {
        $this->assertTrue($this->encoder->supportsEncoding('csv'));
        $this->assertFalse($this->encoder->supportsEncoding('foo'));
    }

    public function testEncode()
    {
        $value = ['foo' => 'hello', 'bar' => 'hey ho'];

        $this->assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCollection()
    {
        $value = [
            ['foo' => 'hello', 'bar' => 'hey ho'],
            ['foo' => 'hi', 'bar' => 'let\'s go'],
        ];

        $this->assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"
hi,"let's go"

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodePlainIndexedArray()
    {
        $this->assertEquals(<<<'CSV'
0,1,2
a,b,c

CSV
            , $this->encoder->encode(['a', 'b', 'c'], 'csv'));
    }

    public function testEncodeNonArray()
    {
        $this->assertEquals(<<<'CSV'
0
foo

CSV
            , $this->encoder->encode('foo', 'csv'));
    }

    public function testEncodeNestedArrays()
    {
        $value = ['foo' => 'hello', 'bar' => [
            ['id' => 'yo', 1 => 'wesh'],
            ['baz' => 'Halo', 'foo' => 'olá'],
        ]];

        $this->assertEquals(<<<'CSV'
foo,bar.0.id,bar.0.1,bar.1.baz,bar.1.foo
hello,yo,wesh,Halo,olá

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettings()
    {
        $this->encoder = new CsvEncoder(';', "'", '|', '-');

        $value = ['a' => 'he\'llo', 'c' => ['d' => 'foo']];

        $this->assertEquals(<<<'CSV'
a;c-d
'he''llo';foo

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettingsPassedInContext()
    {
        $value = ['a' => 'he\'llo', 'c' => ['d' => 'foo']];

        $this->assertSame(<<<'CSV'
a;c-d
'he''llo';foo

CSV
        , $this->encoder->encode($value, 'csv', [
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]));
    }

    public function testEncodeEmptyArray()
    {
        $this->assertEquals("\n\n", $this->encoder->encode([], 'csv'));
        $this->assertEquals("\n\n", $this->encoder->encode([[]], 'csv'));
    }

    public function testEncodeVariableStructure()
    {
        $value = [
            ['a' => ['foo', 'bar']],
            ['a' => [], 'b' => 'baz'],
            ['a' => ['bar', 'foo'], 'c' => 'pong'],
        ];
        $csv = <<<CSV
a.0,a.1,c,b
foo,bar,,
,,,baz
bar,foo,pong,

CSV;

        $this->assertEquals($csv, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomHeaders()
    {
        $context = [
            CsvEncoder::HEADERS_KEY => [
                'b',
                'c',
            ],
        ];
        $value = [
            ['a' => 'foo', 'b' => 'bar'],
        ];
        $csv = <<<CSV
b,c,a
bar,,foo

CSV;

        $this->assertEquals($csv, $this->encoder->encode($value, 'csv', $context));
    }

    public function testSupportsDecoding()
    {
        $this->assertTrue($this->encoder->supportsDecoding('csv'));
        $this->assertFalse($this->encoder->supportsDecoding('foo'));
    }

    public function testDecode()
    {
        $expected = ['foo' => 'a', 'bar' => 'b'];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
CSV
        , 'csv'));
    }

    public function testDecodeCollection()
    {
        $expected = [
            ['foo' => 'a', 'bar' => 'b'],
            ['foo' => 'c', 'bar' => 'd'],
            ['foo' => 'f'],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
c,d
f

CSV
        , 'csv'));
    }

    public function testDecodeToManyRelation()
    {
        $expected = [
            ['foo' => 'bar', 'relations' => [['a' => 'b'], ['a' => 'b']]],
            ['foo' => 'bat', 'relations' => [['a' => 'b'], ['a' => '']]],
            ['foo' => 'bat', 'relations' => [['a' => 'b']]],
            ['foo' => 'baz', 'relations' => [['a' => 'c'], ['a' => 'c']]],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,relations.0.a,relations.1.a
bar,b,b
bat,b,
bat,b
baz,c,c
CSV
            , 'csv'));
    }

    public function testDecodeNestedArrays()
    {
        $expected = [
            ['foo' => 'a', 'bar' => ['baz' => ['bat' => 'b']]],
            ['foo' => 'c', 'bar' => ['baz' => ['bat' => 'd']]],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar.baz.bat
a,b
c,d
CSV
        , 'csv'));
    }

    public function testDecodeCustomSettings()
    {
        $this->encoder = new CsvEncoder(';', "'", '|', '-');

        $expected = ['a' => 'hell\'o', 'bar' => ['baz' => 'b']];
        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
        , 'csv'));
    }

    public function testDecodeCustomSettingsPassedInContext()
    {
        $expected = ['a' => 'hell\'o', 'bar' => ['baz' => 'b']];
        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
        , 'csv', [
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]));
    }

    public function testDecodeMalformedCollection()
    {
        $expected = [
            ['foo' => 'a', 'bar' => 'b'],
            ['foo' => 'c', 'bar' => 'd'],
            ['foo' => 'f'],
        ];

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b,e
c,d,g,h
f

CSV
            , 'csv'));
    }

    public function testDecodeEmptyArray()
    {
        $this->assertEquals([], $this->encoder->decode('', 'csv'));
    }
}
