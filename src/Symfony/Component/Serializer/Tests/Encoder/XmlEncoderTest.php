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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\EnvelopedMessage;
use Symfony\Component\Serializer\Tests\Fixtures\EnvelopedMessageNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\EnvelopeNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\EnvelopeObject;
use Symfony\Component\Serializer\Tests\Fixtures\NormalizableTraversableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy;

class XmlEncoderTest extends TestCase
{
    private XmlEncoder $encoder;
    private string $exampleDateTimeString = '2017-02-19T15:16:08+0300';

    protected function setUp(): void
    {
        $this->encoder = new XmlEncoder();
        $serializer = new Serializer([new CustomNormalizer()], ['xml' => new XmlEncoder()]);
        $this->encoder->setSerializer($serializer);
    }

    /**
     * @dataProvider validEncodeProvider
     */
    public function testEncode(string $expected, mixed $data, array $context = [])
    {
        $this->assertSame($expected, $this->encoder->encode($data, 'xml', $context));
    }

    /**
     * @return iterable<array{0: string, 1: mixed, 2?: array}>
     */
    public static function validEncodeProvider(): iterable
    {
        $obj = new ScalarDummy();
        $obj->xmlFoo = 'foo';

        yield 'encode scalar' => [
            '<?xml version="1.0"?>'."\n"
            .'<response>foo</response>'."\n",
            $obj,
        ];

        yield 'encode array object' => [
            '<?xml version="1.0"?>'."\n"
            .'<response><foo>bar</foo></response>'."\n",
            new \ArrayObject(['foo' => 'bar']),
        ];

        yield 'encode empty array object' => [
            '<?xml version="1.0"?>'."\n".'<response/>'."\n",
            new \ArrayObject(),
        ];

        $obj = new ScalarDummy();
        $obj->xmlFoo = [
            'foo-bar' => [
                '@id' => 1,
                '@name' => 'Bar',
            ],
            'Foo' => [
                'Bar' => 'Test',
                '@Type' => 'test',
            ],
            'föo_bär' => 'a',
            'Bar' => [1, 2, 3],
            'a' => 'b',
            'scalars' => [
                '@bool-true' => true,
                '@bool-false' => false,
                '@int' => 3,
                '@float' => 3.4,
                '@sring' => 'a',
            ],
        ];

        yield 'attributes' => [
            '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo-bar id="1" name="Bar"/>'.
            '<Foo Type="test"><Bar>Test</Bar></Foo>'.
            '<föo_bär>a</föo_bär>'.
            '<Bar>1</Bar>'.
            '<Bar>2</Bar>'.
            '<Bar>3</Bar>'.
            '<a>b</a>'.
            '<scalars bool-true="1" bool-false="0" int="3" float="3.4" sring="a"/>'.
            '</response>'."\n",
            $obj,
        ];

        $obj = new ScalarDummy();
        $obj->xmlFoo = [
            'foo-bar' => 'a',
            'foo_bar' => 'a',
            'föo_bär' => 'a',
        ];

        yield 'element name valid' => [
            '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo-bar>a</foo-bar>'.
            '<foo_bar>a</foo_bar>'.
            '<föo_bär>a</föo_bär>'.
            '</response>'."\n",
            $obj,
        ];

        $xml = simplexml_load_string('<firstname>Peter</firstname>');
        $array = ['person' => $xml];

        yield 'encode SimpleXML' => [
            '<?xml version="1.0"?>'."\n".
            '<response><person><firstname>Peter</firstname></person></response>'."\n",
            $array,
        ];

        $xml = simplexml_load_string('<firstname>Peter</firstname>');
        $array = ['person' => $xml];

        yield 'encode XML attributes' => [
            '<?xml version="1.1" encoding="utf-8" standalone="yes"?>'."\n".
            '<response><person><firstname>Peter</firstname></person></response>'."\n",
            $array,
            [
                'xml_version' => '1.1',
                'xml_encoding' => 'utf-8',
                'xml_standalone' => true,
            ],
        ];

        yield 'encode remvoing empty tags' => [
            '<?xml version="1.0"?>'."\n".
            '<response><person><firstname>Peter</firstname></person></response>'."\n",
            ['person' => ['firstname' => 'Peter', 'lastname' => null]],
            ['remove_empty_tags' => true],
        ];

        yield 'encode not removing empty tags' => [
            '<?xml version="1.0"?>'."\n".
            '<response><person><firstname>Peter</firstname><lastname/></person></response>'."\n",
            ['person' => ['firstname' => 'Peter', 'lastname' => null]],
        ];

        yield 'encode with context' => [
            <<<'XML'
<?xml version="1.0"?>
<response>
  <person>
    <name>George Abitbol</name>
    <age></age>
  </person>
</response>

XML,
            ['person' => ['name' => 'George Abitbol', 'age' => null]],
            [
                'xml_format_output' => true,
                'save_options' => \LIBXML_NOEMPTYTAG,
            ],
        ];

        yield 'encode scalar root attributes' => [
            '<?xml version="1.0"?>'."\n".
            '<response eye-color="brown">Paul</response>'."\n",
            [
                '#' => 'Paul',
                '@eye-color' => 'brown',
            ],
        ];

        yield 'encode root attributes' => [
            '<?xml version="1.0"?>'."\n".
            '<response eye-color="brown"><firstname>Paul</firstname></response>'."\n",
            [
                'firstname' => 'Paul',
                '@eye-color' => 'brown',
            ],
        ];

        yield 'encode with CDATA wrapping with default pattern #1' => [
            '<?xml version="1.0"?>'."\n".
            '<response><firstname><![CDATA[Paul & Martha <or Me>]]></firstname></response>'."\n",
            ['firstname' => 'Paul & Martha <or Me>'],
        ];

        yield 'encode with CDATA wrapping with default pattern #2' => [
            '<?xml version="1.0"?>'."\n".
            '<response><lastname>O\'Donnel</lastname></response>'."\n",
            ['lastname' => 'O\'Donnel'],
        ];

        yield 'encode with CDATA wrapping with default pattern #3' => [
            '<?xml version="1.0"?>'."\n".
            '<response><firstname><![CDATA[Paul & Martha]]></firstname></response>'."\n",
            ['firstname' => 'Paul & Martha'],
        ];

        yield 'encode with CDATA wrapping with custom pattern #1' => [
            '<?xml version="1.0"?>'."\n".
            '<response><firstname><![CDATA[Paul & Martha <or Me>]]></firstname></response>'."\n",
            ['firstname' => 'Paul & Martha <or Me>'],
            ['cdata_wrapping_pattern' => '/[<>&"\']/'],
        ];

        yield 'encode with CDATA wrapping with custom pattern #2' => [
            '<?xml version="1.0"?>'."\n".
            '<response><lastname><![CDATA[O\'Donnel]]></lastname></response>'."\n",
            ['lastname' => 'O\'Donnel'],
            ['cdata_wrapping_pattern' => '/[<>&"\']/'],
        ];

        yield 'encode with CDATA wrapping with custom pattern #3' => [
            '<?xml version="1.0"?>'."\n".
            '<response><firstname>Paul and Martha</firstname></response>'."\n",
            ['firstname' => 'Paul and Martha'],
            ['cdata_wrapping_pattern' => '/[<>&"\']/'],
        ];

        yield 'enable CDATA wrapping' => [
            '<?xml version="1.0"?>'."\n".
            '<response><firstname><![CDATA[Paul & Martha <or Me>]]></firstname></response>'."\n",
            ['firstname' => 'Paul & Martha <or Me>'],
            ['cdata_wrapping' => true],
        ];

        yield 'disable CDATA wrapping' => [
            '<?xml version="1.0"?>'."\n".
            '<response><firstname>Paul &amp; Martha &lt;or Me&gt;</firstname></response>'."\n",
            ['firstname' => 'Paul & Martha <or Me>'],
            ['cdata_wrapping' => false],
        ];

        yield 'encode scalar with attribute' => [
            '<?xml version="1.0"?>'."\n".
            '<response><person eye-color="brown">Peter</person></response>'."\n",
            ['person' => ['@eye-color' => 'brown', '#' => 'Peter']],
        ];

        yield 'encode' => [
            self::getXmlSource(),
            self::getObject(),
        ];

        yield 'encode with namespace' => [
            self::getNamespacedXmlSource(),
            self::getNamespacedArray(),
        ];
    }

    public function testEncodeSerializerXmlRootNodeNameOption()
    {
        $options = ['xml_root_node_name' => 'test'];
        $this->encoder = new XmlEncoder();
        $serializer = new Serializer([], ['xml' => new XmlEncoder()]);
        $this->encoder->setSerializer($serializer);

        $array = [
            'person' => ['@eye-color' => 'brown', '#' => 'Peter'],
        ];

        $expected = '<?xml version="1.0"?>'."\n".
            '<test><person eye-color="brown">Peter</person></test>'."\n";

        $this->assertSame($expected, $serializer->serialize($array, 'xml', $options));
    }

    public function testEncodeTraversableWhenNormalizable()
    {
        $this->encoder = new XmlEncoder();
        $serializer = new Serializer([new CustomNormalizer()], ['xml' => new XmlEncoder()]);
        $this->encoder->setSerializer($serializer);

        $expected = <<<'XML'
<?xml version="1.0"?>
<response><foo>normalizedFoo</foo><bar>normalizedBar</bar></response>

XML;

        $this->assertSame($expected, $serializer->serialize(new NormalizableTraversableDummy(), 'xml'));
    }

    public function testDocTypeIsNotAllowed()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Document types are not allowed.');
        $this->encoder->decode('<?xml version="1.0"?><!DOCTYPE foo><foo></foo>', 'foo');
    }

    public function testDecodeScalar()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<response>foo</response>'."\n";

        $this->assertSame('foo', $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeBigDigitAttributes()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document index="182077241760011681341821060401202210011000045913000000017100">Name</document>
XML;

        $this->assertSame(['@index' => 182077241760011681341821060401202210011000045913000000017100, '#' => 'Name'], $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeNegativeIntAttribute()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document index="-1234">Name</document>
XML;

        $this->assertSame(['@index' => -1234, '#' => 'Name'], $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeFloatAttribute()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document index="12.11">Name</document>
XML;

        $this->assertSame(['@index' => 12.11, '#' => 'Name'], $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeNegativeFloatAttribute()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document index="-12.11">Name</document>
XML;

        $this->assertSame(['@index' => -12.11, '#' => 'Name'], $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeFloatAttributeWithZeroWholeNumber()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document index="0.123">Name</document>
XML;

        $this->assertSame(['@index' => 0.123, '#' => 'Name'], $this->encoder->decode($source, 'xml'));
    }

    public function testNoTypeCastRootAttribute()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document a="123"></document>
XML;

        $data = $this->encoder->decode($source, 'xml', ['xml_type_cast_attributes' => false]);
        $expected = [
            '@a' => '123',
            '#' => '',
        ];
        $this->assertSame($expected, $data);
    }

    public function testNoTypeCastAttribute()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document a="018" b="-12.11">
    <node a="018" b="-12.11"/>
</document>
XML;

        $data = $this->encoder->decode($source, 'xml', ['xml_type_cast_attributes' => false]);
        $expected = [
            '@a' => '018',
            '@b' => '-12.11',
            'node' => [
                '@a' => '018',
                '@b' => '-12.11',
                '#' => '',
            ],
        ];
        $this->assertSame($expected, $data);
    }

    public function testDoesNotTypeCastStringsStartingWith0()
    {
        $source = <<<XML
<?xml version="1.0"?>
<document a="018"></document>
XML;

        $data = $this->encoder->decode($source, 'xml');
        $this->assertSame('018', $data['@a']);
    }

    public function testEncodeException()
    {
        $this->expectException(NotEncodableValueException::class);
        $this->encoder->encode('Invalid character: '.\chr(7), 'xml');
    }

    public function testDecode()
    {
        $source = $this->getXmlSource();
        $obj = $this->getObject();

        $this->assertEquals(get_object_vars($obj), $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeCdataWrapping()
    {
        $expected = [
            'firstname' => 'Paul <or Me>',
        ];

        $xml = '<?xml version="1.0"?>'."\n".
            '<response><firstname><![CDATA[Paul <or Me>]]></firstname></response>'."\n";

        $this->assertEquals($expected, $this->encoder->decode($xml, 'xml'));
    }

    public function testDecodeCdataWrappingAndWhitespace()
    {
        $expected = [
            'firstname' => 'Paul <or Me>',
        ];

        $xml = '<?xml version="1.0"?>'."\n".
            '<response><firstname>'."\n".
                '<![CDATA[Paul <or Me>]]></firstname></response>'."\n";

        $this->assertEquals($expected, $this->encoder->decode($xml, 'xml'));
    }

    public function testDecodeWithNamespace()
    {
        $source = $this->getNamespacedXmlSource();
        $array = $this->getNamespacedArray();

        $this->assertEquals($array, $this->encoder->decode($source, 'xml'));

        $source = '<?xml version="1.0"?>'."\n".
            '<response xmlns="http://www.w3.org/2005/Atom" xmlns:app="http://www.w3.org/2007/app" app:foo="bar">'.
            '</response>'."\n";

        $this->assertEquals([
            '@xmlns' => 'http://www.w3.org/2005/Atom',
            '@xmlns:app' => 'http://www.w3.org/2007/app',
            '@app:foo' => 'bar',
            '#' => '',
        ], $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeScalarWithAttribute()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<response><person eye-color="brown">Peter</person></response>'."\n";

        $expected = [
            'person' => ['@eye-color' => 'brown', '#' => 'Peter'],
        ];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeScalarRootAttributes()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<person eye-color="brown">Peter</person>'."\n";

        $expected = [
            '#' => 'Peter',
            '@eye-color' => 'brown',
        ];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeRootAttributes()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<person eye-color="brown"><firstname>Peter</firstname><lastname>Mac Calloway</lastname></person>'."\n";

        $expected = [
            'firstname' => 'Peter',
            'lastname' => 'Mac Calloway',
            '@eye-color' => 'brown',
        ];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeArray()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<people>'.
            '<person><firstname>Benjamin</firstname><lastname>Alexandre</lastname></person>'.
            '<person><firstname>Damien</firstname><lastname>Clay</lastname></person>'.
            '</people>'.
            '</response>'."\n";

        $expected = [
            'people' => ['person' => [
                ['firstname' => 'Benjamin', 'lastname' => 'Alexandre'],
                ['firstname' => 'Damien', 'lastname' => 'Clay'],
            ]],
        ];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeXMLWithProcessInstruction()
    {
        $source = <<<'XML'
<?xml version="1.0"?>
<?xml-stylesheet type="text/xsl" href="/xsl/xmlverbatimwrapper.xsl"?>
    <?display table-view?>
    <?sort alpha-ascending?>
    <response>
        <foo>foo</foo>
        <?textinfo whitespace is allowed ?>
        <bar>a</bar>
        <bar>b</bar>
        <baz>
            <key>val</key>
            <key2>val</key2>
            <item key="A B">bar</item>
            <item>
                <title>title1</title>
            </item>
            <?item ignore-title ?>
            <item>
                <title>title2</title>
            </item>
            <Barry>
                <FooBar id="1">
                    <Baz>Ed</Baz>
                </FooBar>
            </Barry>
        </baz>
        <qux>1</qux>
    </response>
    <?instruction <value> ?>
XML;
        $obj = $this->getObject();

        $this->assertEquals(get_object_vars($obj), $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeIgnoreWhiteSpace()
    {
        $source = <<<'XML'
<?xml version="1.0"?>
<people>
    <person>
        <firstname>Benjamin</firstname>
        <lastname>Alexandre</lastname>
    </person>
    <person>
        <firstname>Damien</firstname>
        <lastname>Clay</lastname>
    </person>
</people>
XML;
        $expected = ['person' => [
            ['firstname' => 'Benjamin', 'lastname' => 'Alexandre'],
            ['firstname' => 'Damien', 'lastname' => 'Clay'],
        ]];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeIgnoreComments()
    {
        $source = <<<'XML'
<?xml version="1.0"?>
<!-- This comment should not become the root node. -->
<people>
    <person>
        <!-- Even if the first comment didn't become the root node, we don't
             want this comment either. -->
        <firstname>Benjamin</firstname>
        <lastname>Alexandre</lastname>
    </person>
    <person>
        <firstname>Damien</firstname>
        <lastname>Clay</lastname>
    </person>
</people>
XML;

        $expected = ['person' => [
            ['firstname' => 'Benjamin', 'lastname' => 'Alexandre'],
            ['firstname' => 'Damien', 'lastname' => 'Clay'],
        ]];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeIgnoreDocumentType()
    {
        $source = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE people>
<people>
    <person>
        <firstname>Benjamin</firstname>
        <lastname>Alexandre</lastname>
    </person>
    <person>
        <firstname>Damien</firstname>
        <lastname>Clay</lastname>
    </person>
</people>
XML;
        $expected = ['person' => [
            ['firstname' => 'Benjamin', 'lastname' => 'Alexandre'],
            ['firstname' => 'Damien', 'lastname' => 'Clay'],
        ]];
        $this->assertEquals($expected, $this->encoder->decode(
            $source,
            'xml',
            [XmlEncoder::DECODER_IGNORED_NODE_TYPES => [\XML_DOCUMENT_TYPE_NODE]]
        ));
    }

    public function testDecodePreserveComments()
    {
        $source = <<<'XML'
<?xml version="1.0"?>
<people>
    <person>
        <!-- This comment should be decoded. -->
        <firstname>Benjamin</firstname>
        <lastname>Alexandre</lastname>
    </person>
    <person>
        <firstname>Damien</firstname>
        <lastname>Clay</lastname>
    </person>
</people>
XML;

        $this->encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'people',
            XmlEncoder::DECODER_IGNORED_NODE_TYPES => [\XML_PI_NODE],
        ]);
        $serializer = new Serializer([new CustomNormalizer()], ['xml' => new XmlEncoder()]);
        $this->encoder->setSerializer($serializer);

        $expected = ['person' => [
            ['firstname' => 'Benjamin', 'lastname' => 'Alexandre', '#comment' => ' This comment should be decoded. '],
            ['firstname' => 'Damien', 'lastname' => 'Clay'],
        ]];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeAlwaysAsCollection()
    {
        $this->encoder = new XmlEncoder([XmlEncoder::ROOT_NODE_NAME => 'response']);
        $serializer = new Serializer([new CustomNormalizer()], ['xml' => new XmlEncoder()]);
        $this->encoder->setSerializer($serializer);

        $source = <<<'XML'
<?xml version="1.0"?>
<order_rows nodeType="order_row" virtualEntity="true">
    <order_row>
        <id><![CDATA[16]]></id>
        <test><![CDATA[16]]></test>
    </order_row>
</order_rows>
XML;
        $expected = [
            '@nodeType' => 'order_row',
            '@virtualEntity' => 'true',
            'order_row' => [[
                'id' => [16],
                'test' => [16],
            ]],
        ];

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml', ['as_collection' => true]));
    }

    public function testDecodeWithoutItemHash()
    {
        $obj = new ScalarDummy();
        $obj->xmlFoo = [
            'foo-bar' => [
                '@key' => 'value',
                'item' => ['@key' => 'key', 'key-val' => 'val'],
            ],
            'Foo' => [
                'Bar' => 'Test',
                '@Type' => 'test',
            ],
            'föo_bär' => 'a',
            'Bar' => [1, 2, 3],
            'a' => 'b',
        ];
        $expected = [
            'foo-bar' => [
                '@key' => 'value',
                'key' => ['@key' => 'key', 'key-val' => 'val'],
            ],
            'Foo' => [
                'Bar' => 'Test',
                '@Type' => 'test',
            ],
            'föo_bär' => 'a',
            'Bar' => [1, 2, 3],
            'a' => 'b',
        ];
        $xml = $this->encoder->encode($obj, 'xml');
        $this->assertEquals($expected, $this->encoder->decode($xml, 'xml'));
    }

    public function testDecodeInvalidXml()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->encoder->decode('<?xml version="1.0"?><invalid><xml>', 'xml');
    }

    public function testPreventsComplexExternalEntities()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->encoder->decode('<?xml version="1.0"?><!DOCTYPE scan[<!ENTITY test SYSTEM "php://filter/read=convert.base64-encode/resource=XmlEncoderTest.php">]><scan>&test;</scan>', 'xml');
    }

    public function testDecodeEmptyXml()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid XML data, it cannot be empty.');
        $this->encoder->decode(' ', 'xml');
    }

    protected static function getXmlSource(): string
    {
        return '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo>foo</foo>'.
            '<bar>a</bar><bar>b</bar>'.
            '<baz><key>val</key><key2>val</key2><item key="A B">bar</item>'.
            '<item><title>title1</title></item><item><title>title2</title></item>'.
            '<Barry><FooBar id="1"><Baz>Ed</Baz></FooBar></Barry></baz>'.
            '<qux>1</qux>'.
            '</response>'."\n";
    }

    protected static function getNamespacedXmlSource(): string
    {
        return '<?xml version="1.0"?>'."\n".
            '<response xmlns="http://www.w3.org/2005/Atom" xmlns:app="http://www.w3.org/2007/app" xmlns:media="http://search.yahoo.com/mrss/" xmlns:gd="http://schemas.google.com/g/2005" xmlns:yt="http://gdata.youtube.com/schemas/2007">'.
            '<qux>1</qux>'.
            '<app:foo>foo</app:foo>'.
            '<yt:bar>a</yt:bar><yt:bar>b</yt:bar>'.
            '<media:baz><media:key>val</media:key><media:key2>val</media:key2><item key="A B">bar</item>'.
            '<item><title>title1</title></item><item><title>title2</title></item>'.
            '<Barry size="large"><FooBar gd:id="1"><Baz>Ed</Baz></FooBar></Barry></media:baz>'.
            '</response>'."\n";
    }

    protected static function getNamespacedArray(): array
    {
        return [
            '@xmlns' => 'http://www.w3.org/2005/Atom',
            '@xmlns:app' => 'http://www.w3.org/2007/app',
            '@xmlns:media' => 'http://search.yahoo.com/mrss/',
            '@xmlns:gd' => 'http://schemas.google.com/g/2005',
            '@xmlns:yt' => 'http://gdata.youtube.com/schemas/2007',
            'qux' => '1',
            'app:foo' => 'foo',
            'yt:bar' => ['a', 'b'],
            'media:baz' => [
                'media:key' => 'val',
                'media:key2' => 'val',
                'A B' => 'bar',
                'item' => [
                    [
                        'title' => 'title1',
                    ],
                    [
                        'title' => 'title2',
                    ],
                ],
                'Barry' => [
                    '@size' => 'large',
                    'FooBar' => [
                        'Baz' => 'Ed',
                        '@gd:id' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return Dummy
     */
    protected static function getObject(): object
    {
        $obj = new Dummy();
        $obj->foo = 'foo';
        $obj->bar = ['a', 'b'];
        $obj->baz = ['key' => 'val', 'key2' => 'val', 'A B' => 'bar', 'item' => [['title' => 'title1'], ['title' => 'title2']], 'Barry' => ['FooBar' => ['Baz' => 'Ed', '@id' => 1]]];
        $obj->qux = '1';

        return $obj;
    }

    public function testEncodeXmlWithBoolValue()
    {
        $expectedXml = <<<'XML'
<?xml version="1.0"?>
<response><foo>1</foo><bar>0</bar></response>

XML;

        $actualXml = $this->encoder->encode(['foo' => true, 'bar' => false], 'xml');

        $this->assertEquals($expectedXml, $actualXml);
    }

    public function testEncodeXmlWithDomNodeValue()
    {
        $expectedXml = <<<'XML'
<?xml version="1.0"?>
<response><foo>bar</foo><bar>foo &amp; bar</bar></response>

XML;
        $document = new \DOMDocument();

        $actualXml = $this->encoder->encode(['foo' => $document->createTextNode('bar'), 'bar' => $document->createTextNode('foo & bar')], 'xml');

        $this->assertEquals($expectedXml, $actualXml);
    }

    public function testEncodeXmlWithDateTimeObjectValue()
    {
        $xmlEncoder = $this->createXmlEncoderWithDateTimeNormalizer();

        $actualXml = $xmlEncoder->encode(['dateTime' => new \DateTimeImmutable($this->exampleDateTimeString)], 'xml');

        $this->assertEquals($this->createXmlWithDateTime(), $actualXml);
    }

    public function testEncodeXmlWithDateTimeObjectField()
    {
        $xmlEncoder = $this->createXmlEncoderWithDateTimeNormalizer();

        $actualXml = $xmlEncoder->encode(['foo' => ['@dateTime' => new \DateTimeImmutable($this->exampleDateTimeString)]], 'xml');

        $this->assertEquals($this->createXmlWithDateTimeField(), $actualXml);
    }

    public function testNotEncodableValueExceptionMessageForAResource()
    {
        $this->expectException(NotEncodableValueException::class);
        $this->expectExceptionMessage('An unexpected value could not be serialized: stream resource');

        (new XmlEncoder())->encode(tmpfile(), 'xml');
    }

    public function testReentrantXmlEncoder()
    {
        $envelope = new EnvelopeObject();
        $message = new EnvelopedMessage();
        $message->text = 'Symfony is great';
        $envelope->message = $message;

        $encoder = $this->createXmlEncoderWithEnvelopeNormalizer();
        $expected = <<<'XML'
<?xml version="1.0"?>
<response><message>PD94bWwgdmVyc2lvbj0iMS4wIj8+CjxyZXNwb25zZT48dGV4dD5TeW1mb255IGlzIGdyZWF0PC90ZXh0PjwvcmVzcG9uc2U+Cg==</message></response>

XML;

        $this->assertSame($expected, $encoder->encode($envelope, 'xml'));
    }

    public function testEncodeComment()
    {
        $expected = <<<'XML'
<?xml version="1.0"?>
<response><!-- foo --></response>

XML;

        $data = ['#comment' => ' foo '];

        $this->assertEquals($expected, $this->encoder->encode($data, 'xml'));
    }

    public function testEncodeWithoutPi()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'response',
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [\XML_PI_NODE],
        ]);

        $expected = '<response/>';

        $this->assertEquals($expected, $encoder->encode([], 'xml'));
    }

    public function testEncodeWithoutComment()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'response',
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [\XML_COMMENT_NODE],
        ]);

        $expected = <<<'XML'
<?xml version="1.0"?>
<response/>

XML;

        $data = ['#comment' => ' foo '];

        $this->assertEquals($expected, $encoder->encode($data, 'xml'));
    }

    private function createXmlEncoderWithEnvelopeNormalizer(): XmlEncoder
    {
        $normalizers = [
            $envelopeNormalizer = new EnvelopeNormalizer(),
            new EnvelopedMessageNormalizer(),
        ];

        $encoder = new XmlEncoder();
        $serializer = new Serializer($normalizers, ['xml' => $encoder]);
        $encoder->setSerializer($serializer);
        $envelopeNormalizer->setSerializer($serializer);

        return $encoder;
    }

    private function createXmlEncoderWithDateTimeNormalizer(): XmlEncoder
    {
        $encoder = new XmlEncoder();
        $serializer = new Serializer([$this->createMockDateTimeNormalizer()], ['xml' => new XmlEncoder()]);
        $encoder->setSerializer($serializer);

        return $encoder;
    }

    private function createMockDateTimeNormalizer(): MockObject&NormalizerInterface
    {
        $mock = $this->createMock(NormalizerInterface::class);

        $mock
            ->expects($this->once())
            ->method('normalize')
            ->with(new \DateTimeImmutable($this->exampleDateTimeString), 'xml', [])
            ->willReturn($this->exampleDateTimeString);

        $mock
            ->expects($this->once())
            ->method('getSupportedTypes')
            ->willReturn([\DateTimeImmutable::class => true]);

        $mock
            ->expects($this->once())
            ->method('supportsNormalization')
            ->with(new \DateTimeImmutable($this->exampleDateTimeString), 'xml')
            ->willReturn(true);

        return $mock;
    }

    private function createXmlWithDateTime(): string
    {
        return \sprintf('<?xml version="1.0"?>
<response><dateTime>%s</dateTime></response>
', $this->exampleDateTimeString);
    }

    private function createXmlWithDateTimeField(): string
    {
        return \sprintf('<?xml version="1.0"?>
<response><foo dateTime="%s"/></response>
', $this->exampleDateTimeString);
    }
}
