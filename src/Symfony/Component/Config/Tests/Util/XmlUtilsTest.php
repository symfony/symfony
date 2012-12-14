<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Loader;

use Symfony\Component\Config\Util\XmlUtils;

class XmlUtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadFile()
    {
        $fixtures = __DIR__.'/../Fixtures/Util/';

        try {
            XmlUtils::loadFile($fixtures.'invalid.xml');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertContains('ERROR 77', $e->getMessage());
        }

        try {
            XmlUtils::loadFile($fixtures.'document_type.xml');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertContains('Document types are not allowed', $e->getMessage());
        }

        try {
            XmlUtils::loadFile($fixtures.'invalid_schema.xml', $fixtures.'schema.xsd');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertContains('ERROR 1845', $e->getMessage());
        }

        try {
            XmlUtils::loadFile($fixtures.'invalid_schema.xml', 'invalid_callback_or_file');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertContains('XSD file or callable', $e->getMessage());
        }

        $mock = $this->getMock(__NAMESPACE__.'\Validator');
        $mock->expects($this->exactly(2))->method('validate')->will($this->onConsecutiveCalls(false, true));

        try {
            XmlUtils::loadFile($fixtures.'valid.xml', array($mock, 'validate'));
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertContains('is not valid', $e->getMessage());
        }

        $this->assertInstanceOf('DOMDocument', XmlUtils::loadFile($fixtures.'valid.xml', array($mock, 'validate')));
    }

    /**
     * @dataProvider getDataForConvertDomToArray
     */
    public function testConvertDomToArray($expected, $xml, $root = false, $checkPrefix = true)
    {
        $dom = new \DOMDocument();
        $dom->loadXML($root ? $xml : '<root>'.$xml.'</root>');

        $this->assertSame($expected, XmlUtils::convertDomElementToArray($dom->documentElement, $checkPrefix));
    }

    public function getDataForConvertDomToArray()
    {
        return array(
            array(null, ''),
            array('bar', 'bar'),
            array(array('bar' => 'foobar'), '<foo bar="foobar" />', true),
            array(array('foo' => null), '<foo />'),
            array(array('foo' => 'bar'), '<foo>bar</foo>'),
            array(array('foo' => array('foo' => 'bar')), '<foo foo="bar"/>'),
            array(array('foo' => array('foo' => 'bar')), '<foo><foo>bar</foo></foo>'),
            array(array('foo' => array('foo' => 'bar', 'value' => 'text')), '<foo foo="bar">text</foo>'),
            array(array('foo' => array('attr' => 'bar', 'foo' => 'text')), '<foo attr="bar"><foo>text</foo></foo>'),
            array(array('foo' => array('bar', 'text')), '<foo>bar</foo><foo>text</foo>'),
            array(array('foo' => array(array('foo' => 'bar'), array('foo' => 'text'))), '<foo foo="bar"/><foo foo="text" />'),
            array(array('foo' => array('foo' => array('bar', 'text'))), '<foo foo="bar"><foo>text</foo></foo>'),
            array(array('foo' => 'bar'), '<foo><!-- Comment -->bar</foo>'),
            array(array('foo' => 'text'), '<foo xmlns:h="http://www.example.org/bar" h:bar="bar">text</foo>'),
            array(array('foo' => array('bar' => 'bar', 'value' => 'text')), '<foo xmlns:h="http://www.example.org/bar" h:bar="bar">text</foo>', false, false),
            array(array('attr' => 1, 'b' => 'hello'), '<foo:a xmlns:foo="http://www.example.org/foo" xmlns:h="http://www.example.org/bar" attr="1" h:bar="bar"><foo:b>hello</foo:b><h:c>2</h:c></foo:a>', true),
        );
    }

    /**
     * @dataProvider getDataForPhpize
     */
    public function testPhpize($expected, $value)
    {
        $this->assertSame($expected, XmlUtils::phpize($value));
    }

    public function getDataForPhpize()
    {
        return array(
            array(null, 'null'),
            array(true, 'true'),
            array(false, 'false'),
            array(null, 'Null'),
            array(true, 'True'),
            array(false, 'False'),
            array(0, '0'),
            array(1, '1'),
            array(0777, '0777'),
            array(255, '0xFF'),
            array(100.0, '1e2'),
            array(-120.0, '-1.2E2'),
            array(-10100.1, '-10100.1'),
            array(-10100.1, '-10,100.1'),
            array('foo', 'foo'),
        );
    }
}

interface Validator
{
    public function validate();
}
