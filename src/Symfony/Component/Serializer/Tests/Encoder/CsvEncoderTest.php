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
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CsvEncoderTest extends TestCase
{
    /**
     * @var CsvEncoder
     */
    private $encoder;

    protected function setUp(): void
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
            'int_one' => 1,
            'string_one' => '1',
        ];

        // Check that true and false are appropriately handled
        self::assertSame($csv = <<<'CSV'
string,int,false,true,int_one,string_one
foo,2,0,1,1,1

CSV, $this->encoder->encode($data, 'csv'));

        self::assertSame([
            'string' => 'foo',
            'int' => '2',
            'false' => '0',
            'true' => '1',
            'int_one' => '1',
            'string_one' => '1',
        ], $this->encoder->decode($csv, 'csv', [CsvEncoder::AS_COLLECTION_KEY => false]));
    }

    /**
     * @requires PHP 7.4
     */
    public function testDoubleQuotesAndSlashes()
    {
        self::assertSame($csv = <<<'CSV'
0,1,2,3,4,5
,"""","foo""","\""",\,foo\

CSV, $this->encoder->encode($data = ['', '"', 'foo"', '\\"', '\\', 'foo\\'], 'csv'));

        self::assertSame($data, $this->encoder->decode($csv, 'csv', [CsvEncoder::AS_COLLECTION_KEY => false]));
    }

    /**
     * @requires PHP 7.4
     */
    public function testSingleSlash()
    {
        self::assertSame($csv = "0\n\\\n", $this->encoder->encode($data = ['\\'], 'csv'));
        self::assertSame($data, $this->encoder->decode($csv, 'csv', [CsvEncoder::AS_COLLECTION_KEY => false]));
        self::assertSame($data, $this->encoder->decode(trim($csv), 'csv', [CsvEncoder::AS_COLLECTION_KEY => false]));
    }

    public function testSupportEncoding()
    {
        self::assertTrue($this->encoder->supportsEncoding('csv'));
        self::assertFalse($this->encoder->supportsEncoding('foo'));
    }

    public function testEncode()
    {
        $value = ['foo' => 'hello', 'bar' => 'hey ho'];

        self::assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"

CSV, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCollection()
    {
        $value = [
            ['foo' => 'hello', 'bar' => 'hey ho'],
            ['foo' => 'hi', 'bar' => 'let\'s go'],
        ];

        self::assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"
hi,"let's go"

CSV, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodePlainIndexedArray()
    {
        self::assertEquals(<<<'CSV'
0,1,2
a,b,c

CSV, $this->encoder->encode(['a', 'b', 'c'], 'csv'));
    }

    public function testEncodeNonArray()
    {
        self::assertEquals(<<<'CSV'
0
foo

CSV, $this->encoder->encode('foo', 'csv'));
    }

    public function testEncodeNestedArrays()
    {
        $value = ['foo' => 'hello', 'bar' => [
            ['id' => 'yo', 1 => 'wesh'],
            ['baz' => 'Halo', 'foo' => 'olá'],
        ]];

        self::assertEquals(<<<'CSV'
foo,bar.0.id,bar.0.1,bar.1.baz,bar.1.foo
hello,yo,wesh,Halo,olá

CSV, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettings()
    {
        $this->encoder = new CsvEncoder([
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]);

        $value = ['a' => 'he\'llo', 'c' => ['d' => 'foo']];

        self::assertEquals(<<<'CSV'
a;c-d
'he''llo';foo

CSV, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettingsPassedInContext()
    {
        $value = ['a' => 'he\'llo', 'c' => ['d' => 'foo']];

        self::assertSame(<<<'CSV'
a;c-d
'he''llo';foo

CSV, $this->encoder->encode($value, 'csv', [
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]));
    }

    public function testEncodeCustomSettingsPassedInConstructor()
    {
        $encoder = new CsvEncoder([
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]);
        $value = ['a' => 'he\'llo', 'c' => ['d' => 'foo']];

        self::assertSame(<<<'CSV'
a;c-d
'he''llo';foo

CSV, $encoder->encode($value, 'csv'));
    }

    public function testEncodeEmptyArray()
    {
        self::assertEquals("\n\n", $this->encoder->encode([], 'csv'));
        self::assertEquals("\n\n", $this->encoder->encode([[]], 'csv'));
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

        self::assertEquals($csv, $this->encoder->encode($value, 'csv'));
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

        self::assertEquals($csv, $this->encoder->encode($value, 'csv', $context));
    }

    public function testEncodeFormulas()
    {
        $this->encoder = new CsvEncoder([CsvEncoder::ESCAPE_FORMULAS_KEY => true]);

        self::assertSame(<<<'CSV'
0
'=2+3

CSV, $this->encoder->encode(['=2+3'], 'csv'));

        self::assertSame(<<<'CSV'
0
'-2+3

CSV, $this->encoder->encode(['-2+3'], 'csv'));

        self::assertSame(<<<'CSV'
0
'+2+3

CSV, $this->encoder->encode(['+2+3'], 'csv'));

        self::assertSame(<<<'CSV'
0
'@MyDataColumn

CSV, $this->encoder->encode(['@MyDataColumn'], 'csv'));

        self::assertSame(<<<'CSV'
0
"'	tab"

CSV, $this->encoder->encode(["\ttab"], 'csv'));

        self::assertSame(<<<'CSV'
0
"'=1+2"";=1+2"

CSV, $this->encoder->encode(['=1+2";=1+2'], 'csv'));

        self::assertSame(<<<'CSV'
0
"'=1+2'"" ;,=1+2"

CSV, $this->encoder->encode(['=1+2\'" ;,=1+2'], 'csv'));
    }

    public function testDoNotEncodeFormulas()
    {
        self::assertSame(<<<'CSV'
0
=2+3

CSV, $this->encoder->encode(['=2+3'], 'csv'));

        self::assertSame(<<<'CSV'
0
-2+3

CSV, $this->encoder->encode(['-2+3'], 'csv'));

        self::assertSame(<<<'CSV'
0
+2+3

CSV, $this->encoder->encode(['+2+3'], 'csv'));

        self::assertSame(<<<'CSV'
0
@MyDataColumn

CSV, $this->encoder->encode(['@MyDataColumn'], 'csv'));

        self::assertSame(<<<'CSV'
0
"	tab"

CSV, $this->encoder->encode(["\ttab"], 'csv'));

        self::assertSame(<<<'CSV'
0
"=1+2"";=1+2"

CSV, $this->encoder->encode(['=1+2";=1+2'], 'csv'));

        self::assertSame(<<<'CSV'
0
"=1+2'"" ;,=1+2"

CSV, $this->encoder->encode(['=1+2\'" ;,=1+2'], 'csv'));
    }

    public function testEncodeFormulasWithSettingsPassedInContext()
    {
        self::assertSame(<<<'CSV'
0
'=2+3

CSV, $this->encoder->encode(['=2+3'], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));

        self::assertSame(<<<'CSV'
0
'-2+3

CSV, $this->encoder->encode(['-2+3'], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));

        self::assertSame(<<<'CSV'
0
'+2+3

CSV, $this->encoder->encode(['+2+3'], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));

        self::assertSame(<<<'CSV'
0
'@MyDataColumn

CSV, $this->encoder->encode(['@MyDataColumn'], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));

        self::assertSame(<<<'CSV'
0
"'	tab"

CSV, $this->encoder->encode(["\ttab"], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));

        self::assertSame(<<<'CSV'
0
"'=1+2"";=1+2"

CSV, $this->encoder->encode(['=1+2";=1+2'], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));

        self::assertSame(<<<'CSV'
0
"'=1+2'"" ;,=1+2"

CSV, $this->encoder->encode(['=1+2\'" ;,=1+2'], 'csv', [
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
        ]));
    }

    public function testEncodeWithoutHeader()
    {
        self::assertSame(<<<'CSV'
a,b
c,d

CSV, $this->encoder->encode([['a', 'b'], ['c', 'd']], 'csv', [
            CsvEncoder::NO_HEADERS_KEY => true,
        ]));
        $encoder = new CsvEncoder([CsvEncoder::NO_HEADERS_KEY => true]);
        self::assertSame(<<<'CSV'
a,b
c,d

CSV, $encoder->encode([['a', 'b'], ['c', 'd']], 'csv', [
            CsvEncoder::NO_HEADERS_KEY => true,
        ]));
    }

    public function testEncodeArrayObject()
    {
        $value = new \ArrayObject(['foo' => 'hello', 'bar' => 'hey ho']);

        self::assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"

CSV, $this->encoder->encode($value, 'csv'));

        $value = new \ArrayObject();

        self::assertEquals("\n", $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeNestedArrayObject()
    {
        $value = new \ArrayObject(['foo' => new \ArrayObject(['nested' => 'value']), 'bar' => new \ArrayObject(['another' => 'word'])]);

        self::assertEquals(<<<'CSV'
foo.nested,bar.another
value,word

CSV, $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeEmptyArrayObject()
    {
        $value = new \ArrayObject();
        self::assertEquals("\n", $this->encoder->encode($value, 'csv'));

        $value = ['foo' => new \ArrayObject()];
        self::assertEquals("\n\n", $this->encoder->encode($value, 'csv'));
    }

    public function testSupportsDecoding()
    {
        self::assertTrue($this->encoder->supportsDecoding('csv'));
        self::assertFalse($this->encoder->supportsDecoding('foo'));
    }

    public function testDecodeAsSingle()
    {
        $expected = ['foo' => 'a', 'bar' => 'b'];

        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
CSV
            , 'csv', [CsvEncoder::AS_COLLECTION_KEY => false]));
    }

    public function testDecodeCollection()
    {
        $expected = [
            ['foo' => 'a', 'bar' => 'b'],
            ['foo' => 'c', 'bar' => 'd'],
            ['foo' => 'f'],
        ];

        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
c,d
f

CSV
            , 'csv'));
    }

    public function testDecode()
    {
        $expected = [
            ['foo' => 'a'],
        ];

        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo
a

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

        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
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

        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar.baz.bat
a,b
c,d
CSV
            , 'csv'));
    }

    public function testDecodeCustomSettings()
    {
        $this->encoder = new CsvEncoder([
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
        ]);

        $expected = [['a' => 'hell\'o', 'bar' => ['baz' => 'b']]];
        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
            , 'csv'));
    }

    public function testDecodeCustomSettingsPassedInContext()
    {
        $expected = [['a' => 'hell\'o', 'bar' => ['baz' => 'b']]];
        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
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

    public function testDecodeCustomSettingsPassedInConstructor()
    {
        $encoder = new CsvEncoder([
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => "'",
            CsvEncoder::ESCAPE_CHAR_KEY => '|',
            CsvEncoder::KEY_SEPARATOR_KEY => '-',
            CsvEncoder::AS_COLLECTION_KEY => true, // Can be removed in 5.0
        ]);
        $expected = [['a' => 'hell\'o', 'bar' => ['baz' => 'b']]];
        self::assertEquals($expected, $encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
            , 'csv'));
    }

    public function testDecodeMalformedCollection()
    {
        $expected = [
            ['foo' => 'a', 'bar' => 'b'],
            ['foo' => 'c', 'bar' => 'd'],
            ['foo' => 'f'],
        ];

        self::assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b,e
c,d,g,h
f

CSV
            , 'csv'));
    }

    public function testDecodeEmptyArray()
    {
        self::assertEquals([], $this->encoder->decode('', 'csv'));
    }

    public function testDecodeWithoutHeader()
    {
        self::assertEquals([['a', 'b'], ['c', 'd']], $this->encoder->decode(<<<'CSV'
a,b
c,d

CSV
            , 'csv', [
                CsvEncoder::NO_HEADERS_KEY => true,
            ]));
        $encoder = new CsvEncoder([CsvEncoder::NO_HEADERS_KEY => true]);
        self::assertEquals([['a', 'b'], ['c', 'd']], $encoder->decode(<<<'CSV'
a,b
c,d

CSV
            , 'csv', [
                CsvEncoder::NO_HEADERS_KEY => true,
            ]));
    }

    public function testBOMIsAddedOnDemand()
    {
        $value = ['foo' => 'hello', 'bar' => 'hey ho'];

        self::assertEquals("\xEF\xBB\xBF".<<<'CSV'
foo,bar
hello,"hey ho"

CSV, $this->encoder->encode($value, 'csv', [CsvEncoder::OUTPUT_UTF8_BOM_KEY => true]));
    }

    public function testBOMCanNotBeAddedToNonUtf8Csv()
    {
        $value = [mb_convert_encoding('ÄÖÜ', 'ISO-8859-1', 'UTF-8')];

        self::expectException(UnexpectedValueException::class);
        self::expectExceptionMessage('You are trying to add a UTF-8 BOM to a non UTF-8 text.');
        $this->encoder->encode($value, 'csv', [CsvEncoder::OUTPUT_UTF8_BOM_KEY => true]);
    }

    public function testBOMIsStripped()
    {
        $csv = "\xEF\xBB\xBF".<<<'CSV'
foo,bar
hello,"hey ho"

CSV;
        self::assertEquals(['foo' => 'hello', 'bar' => 'hey ho'], $this->encoder->decode($csv, 'csv', [CsvEncoder::AS_COLLECTION_KEY => false]));
    }

    public function testEndOfLine()
    {
        $value = ['foo' => 'hello', 'bar' => 'test'];

        self::assertSame("foo,bar\r\nhello,test\r\n", $this->encoder->encode($value, 'csv', [CsvEncoder::END_OF_LINE => "\r\n"]));
    }

    public function testEndOfLinePassedInConstructor()
    {
        $value = ['foo' => 'hello', 'bar' => 'test'];

        $encoder = new CsvEncoder([CsvEncoder::END_OF_LINE => "\r\n"]);
        self::assertSame("foo,bar\r\nhello,test\r\n", $encoder->encode($value, 'csv'));
    }
}
