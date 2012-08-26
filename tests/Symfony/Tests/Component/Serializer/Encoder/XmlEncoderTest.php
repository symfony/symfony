<?php

namespace Symfony\Tests\Component\Serializer\Encoder;

require_once __DIR__.'/../Fixtures/Dummy.php';
require_once __DIR__.'/../Fixtures/ScalarDummy.php';

use Symfony\Tests\Component\Serializer\Fixtures\Dummy;
use Symfony\Tests\Component\Serializer\Fixtures\ScalarDummy;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class XmlEncoderTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->encoder = new XmlEncoder;
        $serializer = new Serializer(array(new CustomNormalizer), array('xml' => new XmlEncoder()));
        $this->encoder->setSerializer($serializer);
    }

    public function testEncodeScalar()
    {
        $obj = new ScalarDummy;
        $obj->xmlFoo = "foo";

        $expected = '<?xml version="1.0"?>'."\n".
            '<response><![CDATA[foo]]></response>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($obj, 'xml'));
    }

    public function testSetRootNodeName()
    {
        $obj = new ScalarDummy;
        $obj->xmlFoo = "foo";

        $this->encoder->setRootNodeName('test');
        $expected = '<?xml version="1.0"?>'."\n".
            '<test><![CDATA[foo]]></test>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($obj, 'xml'));
    }

    /**
     * @expectedException        UnexpectedValueException
     * @expectedExceptionMessage Document types are not allowed.
     */
    public function testDocTypeIsNotAllowed()
    {
        $this->encoder->decode('<?xml version="1.0"?><!DOCTYPE foo><foo></foo>', 'foo');
    }

    public function testAttributes()
    {
        $obj = new ScalarDummy;
        $obj->xmlFoo = array(
            'foo-bar' => array(
                '@id' => 1,
                '@name' => 'Bar'
            ),
            'Foo' => array(
                'Bar' => "Test",
                '@Type' => 'test'
            ),
            'föo_bär' => 'a',
            "Bar" => array(1,2,3),
            'a' => 'b',
        );
        $expected = '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo-bar id="1" name="Bar"/>'.
            '<Foo Type="test"><Bar><![CDATA[Test]]></Bar></Foo>'.
            '<föo_bär><![CDATA[a]]></föo_bär>'.
            '<Bar>1</Bar>'.
            '<Bar>2</Bar>'.
            '<Bar>3</Bar>'.
            '<a><![CDATA[b]]></a>'.
            '</response>'."\n";
        $this->assertEquals($expected, $this->encoder->encode($obj, 'xml'));
    }

    public function testElementNameValid()
    {
        $obj = new ScalarDummy;
        $obj->xmlFoo = array(
            'foo-bar' => 'a',
            'foo_bar' => 'a',
            'föo_bär' => 'a',
        );

        $expected = '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo-bar><![CDATA[a]]></foo-bar>'.
            '<foo_bar><![CDATA[a]]></foo_bar>'.
            '<föo_bär><![CDATA[a]]></föo_bär>'.
            '</response>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($obj, 'xml'));
    }

    public function testEncodeSimpleXML()
    {
        $xml = simplexml_load_string('<firstname>Peter</firstname>');
        $array = array('person' => $xml);

        $expected = '<?xml version="1.0"?>'."\n".
            '<response><person><firstname>Peter</firstname></person></response>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($array, 'xml'));
    }

    public function testEncodeScalarRootAttributes()
    {
        $array = array(
          '#' => 'Paul',
          '@gender' => 'm'
        );

        $expected = '<?xml version="1.0"?>'."\n".
            '<response gender="m"><![CDATA[Paul]]></response>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($array, 'xml'));
    }

    public function testEncodeRootAttributes()
    {
        $array = array(
          'firstname' => 'Paul',
          '@gender' => 'm'
        );

        $expected = '<?xml version="1.0"?>'."\n".
            '<response gender="m"><firstname><![CDATA[Paul]]></firstname></response>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($array, 'xml'));
    }

    public function testEncodeScalarWithAttribute()
    {
        $array = array(
            'person' => array('@gender' => 'M', '#' => 'Peter'),
        );

        $expected = '<?xml version="1.0"?>'."\n".
            '<response><person gender="M"><![CDATA[Peter]]></person></response>'."\n";

        $this->assertEquals($expected, $this->encoder->encode($array, 'xml'));
    }

    public function testDecodeScalar()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<response>foo</response>'."\n";

        $this->assertEquals('foo', $this->encoder->decode($source, 'xml'));
    }

    public function testEncode()
    {
        $source = $this->getXmlSource();
        $obj = $this->getObject();

        $this->assertEquals($source, $this->encoder->encode($obj, 'xml'));
    }

    public function testDecode()
    {
        $source = $this->getXmlSource();
        $obj = $this->getObject();

        $this->assertEquals(get_object_vars($obj), $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeScalarWithAttribute()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<response><person gender="M">Peter</person></response>'."\n";

        $expected = array(
            'person' => array('@gender' => 'M', '#' => 'Peter'),
        );

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeScalarRootAttributes()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<person gender="M">Peter</person>'."\n";

        $expected = array(
            '#' => 'Peter',
            '@gender' => 'M'
        );

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testDecodeRootAttributes()
    {
        $source = '<?xml version="1.0"?>'."\n".
            '<person gender="M"><firstname>Peter</firstname><lastname>Mac Calloway</lastname></person>'."\n";

        $expected = array(
            'firstname' => 'Peter',
            'lastname' => 'Mac Calloway',
            '@gender' => 'M'
        );

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

        $expected = array(
            'people' => array('person' => array(
                array('firstname' => 'Benjamin', 'lastname' => 'Alexandre'),
                array('firstname' => 'Damien', 'lastname' => 'Clay')
            ))
        );

        $this->assertEquals($expected, $this->encoder->decode($source, 'xml'));
    }

    public function testPreventsComplexExternalEntities()
    {
        $oldCwd = getcwd();
        chdir(__DIR__);

        try {
            $this->encoder->decode('<?xml version="1.0"?><!DOCTYPE scan[<!ENTITY test SYSTEM "php://filter/read=convert.base64-encode/resource=XmlEncoderTest.php">]><scan>&test;</scan>', 'xml');
            chdir($oldCwd);

            $this->fail('No exception was thrown.');
        } catch (\Exception $e) {
            chdir($oldCwd);

            if (!$e instanceof UnexpectedValueException) {
                $this->fail('Expected UnexpectedValueException');
            }
        }
    }

    protected function getXmlSource()
    {
        return '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo><![CDATA[foo]]></foo>'.
            '<bar><![CDATA[a]]></bar><bar><![CDATA[b]]></bar>'.
            '<baz><key><![CDATA[val]]></key><key2><![CDATA[val]]></key2><item key="A B"><![CDATA[bar]]></item>'.
            '<Barry><FooBar id="1"><Baz><![CDATA[Ed]]></Baz></FooBar></Barry></baz>'.
            '<qux>1</qux>'.
            '</response>'."\n";
    }

    protected function getObject()
    {
        $obj = new Dummy;
        $obj->foo = 'foo';
        $obj->bar = array('a', 'b');
        $obj->baz = array('key' => 'val', 'key2' => 'val', 'A B' => 'bar', "Barry" => array('FooBar' => array("Baz"=>"Ed", "@id"=>1)));
        $obj->qux = "1";

        return $obj;
    }
}
