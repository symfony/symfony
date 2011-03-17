<?php

namespace Symfony\Tests\Component\Serializer\Encoder;

require_once __DIR__.'/../Fixtures/Dummy.php';
require_once __DIR__.'/../Fixtures/ScalarDummy.php';

use Symfony\Tests\Component\Serializer\Fixtures\Dummy;
use Symfony\Tests\Component\Serializer\Fixtures\ScalarDummy;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
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
        $serializer = new Serializer;
        $this->encoder = new XmlEncoder;
        $serializer->setEncoder('xml', $this->encoder);
        $serializer->addNormalizer(new CustomNormalizer);
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
            'föo_bär' => '',
            "Bar" => array(1,2,3),
            'a' => 'b',
        );
        $expected = '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo-bar id="1" name="Bar"/>'.
            '<Foo Type="test"><Bar><![CDATA[Test]]></Bar></Foo>'.
            '<föo_bär><![CDATA[]]></föo_bär>'.
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
            'foo-bar' => '',
            'foo_bar' => '',
            'föo_bär' => '',
        );

        $expected = '<?xml version="1.0"?>'."\n".
            '<response>'.
            '<foo-bar><![CDATA[]]></foo-bar>'.
            '<foo_bar><![CDATA[]]></foo_bar>'.
            '<föo_bär><![CDATA[]]></föo_bär>'.
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
        $obj->baz = array('key' => 'val', 'key2' => 'val', 'A B' => 'bar', "Barry" => array('FooBar' => array("@id"=>1,"Baz"=>"Ed")));
        $obj->qux = "1";
        return $obj;
    }
}
