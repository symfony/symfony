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

use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CsvEncoderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CsvEncoder
     */
    private $encoder;

    protected function setUp()
    {
        $this->encoder = new CsvEncoder();
    }

    public function testSupportEncoding()
    {
        $this->assertTrue($this->encoder->supportsEncoding('csv'));
        $this->assertFalse($this->encoder->supportsEncoding('foo'));
    }

    public function testEncode()
    {
        $value = array('foo' => 'hello', 'bar' => 'hey ho');

        $this->assertEquals(<<<'CSV'
foo,bar
hello,"hey ho"

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCollection()
    {
        $value = array(
            array('foo' => 'hello', 'bar' => 'hey ho'),
            array('foo' => 'hi', 'bar' => 'let\'s go'),
        );

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
            , $this->encoder->encode(array('a', 'b', 'c'), 'csv'));
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
        $value = array('foo' => 'hello', 'bar' => array(
            array('id' => 'yo', 1 => 'wesh'),
            array('baz' => 'Halo', 'foo' => 'olá'),
        ));

        $this->assertEquals(<<<'CSV'
foo,bar.0.id,bar.0.1,bar.1.baz,bar.1.foo
hello,yo,wesh,Halo,olá

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeCustomSettings()
    {
        $this->encoder = new CsvEncoder(';', "'", '|', '-');

        $value = array('a' => 'he\'llo', 'c' => array('d' => 'foo'));

        $this->assertEquals(<<<'CSV'
a;c-d
'he''llo';foo

CSV
    , $this->encoder->encode($value, 'csv'));
    }

    public function testEncodeEmptyArray()
    {
        $this->assertEquals("\n\n", $this->encoder->encode(array(), 'csv'));
        $this->assertEquals("\n\n", $this->encoder->encode(array(array()), 'csv'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testEncodeNonFlattenableStructure()
    {
        $this->encoder->encode(array(array('a' => array('foo', 'bar')), array('a' => array())), 'csv');
    }

    public function testSupportsDecoding()
    {
        $this->assertTrue($this->encoder->supportsDecoding('csv'));
        $this->assertFalse($this->encoder->supportsDecoding('foo'));
    }

    public function testDecode()
    {
        $expected = array('foo' => 'a', 'bar' => 'b');

        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
foo,bar
a,b
CSV
        , 'csv'));
    }

    public function testDecodeCollection()
    {
        $expected = array(
            array('foo' => 'a', 'bar' => 'b'),
            array('foo' => 'c', 'bar' => 'd'),
            array('foo' => 'f'),
        );

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
        $expected = array(
            array('foo' => 'bar', 'relations' => array(array('a' => 'b'), array('a' => 'b'))),
            array('foo' => 'bat', 'relations' => array(array('a' => 'b'), array('a' => ''))),
            array('foo' => 'bat', 'relations' => array(array('a' => 'b'))),
            array('foo' => 'baz', 'relations' => array(array('a' => 'c'), array('a' => 'c'))),
        );

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
        $expected = array(
            array('foo' => 'a', 'bar' => array('baz' => array('bat' => 'b'))),
            array('foo' => 'c', 'bar' => array('baz' => array('bat' => 'd'))),
        );

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

        $expected = array('a' => 'hell\'o', 'bar' => array('baz' => 'b'));
        $this->assertEquals($expected, $this->encoder->decode(<<<'CSV'
a;bar-baz
'hell''o';b;c
CSV
        , 'csv'));
    }

    public function testDecodeMalformedCollection()
    {
        $expected = array(
            array('foo' => 'a', 'bar' => 'b'),
            array('foo' => 'c', 'bar' => 'd'),
            array('foo' => 'f'),
        );

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
        $this->assertEquals(array(), $this->encoder->decode('', 'csv'));
    }
}
