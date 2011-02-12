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
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
            '<bar><item key="0"><![CDATA[a]]></item><item key="1"><![CDATA[b]]></item></bar>'.
            '<baz><key><![CDATA[val]]></key><key2><![CDATA[val]]></key2><item key="A B"><![CDATA[bar]]></item></baz>'.
            '<qux>1</qux>'.
            '</response>'."\n";
    }

    protected function getObject()
    {
        $obj = new Dummy;
        $obj->foo = 'foo';
        $obj->bar = array('a', 'b');
        $obj->baz = array('key' => 'val', 'key2' => 'val', 'A B' => 'bar');
        $obj->qux = "1";
        return $obj;
    }
}
