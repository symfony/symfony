<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Polyfill\Tests\Functions;

use Symfony\Component\Polyfill\Functions\Mbstring as p;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @covers Symfony\Component\Polyfill\Functions\Mbstring::<!public>
 */
class MbstringTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_internal_encoding
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_list_encodings
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_substitute_character
     */
    public function testStubs()
    {
        $this->assertFalse(@mb_substitute_character('?'));
        $this->assertTrue(mb_substitute_character('none'));
        $this->assertSame('none', mb_substitute_character());

        $this->assertContains('UTF-8', mb_list_encodings());

        $this->assertTrue(mb_internal_encoding('utf8'));
        $this->assertFalse(@mb_internal_encoding('no-no'));
        $this->assertSame('UTF-8', mb_internal_encoding());
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_convert_encoding
     */
    public function testConvertEncoding()
    {
        $this->assertSame(utf8_decode('déjà'), mb_convert_encoding('déjà', 'Windows-1252'));
        $this->assertSame(base64_encode('déjà'), mb_convert_encoding('déjà', 'Base64'));
        $this->assertSame('&#23455;<&>d&eacute;j&agrave;', mb_convert_encoding('実<&>déjà', 'Html-entities'));
        $this->assertSame('déjà', mb_convert_encoding(base64_encode('déjà'), 'Utf-8', 'Base64'));
        $this->assertSame('déjà', mb_convert_encoding('d&eacute;j&#224;', 'Utf-8', 'Html-entities'));
        $this->assertSame('déjà', mb_convert_encoding(utf8_decode('déjà'), 'Utf-8', 'ASCII,ISO-2022-JP,UTF-8,ISO-8859-1'));
        if (!defined('HHVM_VERSION')) {
            $this->assertSame('déjà', mb_convert_encoding(utf8_decode('déjà'), 'Utf-8', array('ASCII', 'ISO-2022-JP', 'UTF-8', 'ISO-8859-1')));
        }
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strtolower
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strtoupper
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_convert_case
     */
    public function testStrCase()
    {
        $this->assertSame('déjà σσς iiıi', mb_strtolower('DÉJÀ Σσς İIıi'));
        $this->assertSame('DÉJÀ ΣΣΣ İIII', mb_strtoupper('Déjà Σσς İIıi'));
        $this->assertSame('Déjà Σσσ Iı Ii İi', mb_convert_case('DÉJÀ ΣΣΣ ıı iI İİ', MB_CASE_TITLE));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strlen
     */
    public function testStrlen()
    {
        $this->assertSame(3, mb_strlen('한국어'));
        $this->assertSame(8, mb_strlen(\Normalizer::normalize('한국어', \Normalizer::NFD)));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_substr
     */
    public function testSubstr()
    {
        $c = 'déjà';

        $this->assertSame('jà', mb_substr($c,  2));
        $this->assertSame('jà', mb_substr($c, -2));
        $this->assertSame('jà', mb_substr($c, -2, 3));
        $this->assertSame('',   mb_substr($c, -1,  0));
        $this->assertSame('',   mb_substr($c,  1, -4));
        $this->assertSame('j',  mb_substr($c, -2, -1));
        $this->assertSame('',   mb_substr($c, -2, -2));
        $this->assertSame('',   mb_substr($c,  5,  0));
        $this->assertSame('',   mb_substr($c, -5,  0));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strpos
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_stripos
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strrpos
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strripos
     */
    public function testStrpos()
    {
        $this->assertSame(false, @mb_strpos('abc', ''));
        $this->assertSame(false, @mb_strpos('abc', 'a', -1));
        $this->assertSame(false, mb_strpos('abc', 'd'));
        $this->assertSame(false, mb_strpos('abc', 'a', 3));
        $this->assertSame(1, mb_strpos('한국어', '국'));
        $this->assertSame(3, mb_stripos('DÉJÀ', 'à'));
        $this->assertSame(false, mb_strrpos('한국어', ''));
        $this->assertSame(1, mb_strrpos('한국어', '국'));
        $this->assertSame(3, mb_strripos('DÉJÀ', 'à'));
        $this->assertSame(1, mb_stripos('aςσb', 'ΣΣ'));
        $this->assertSame(1, mb_strripos('aςσb', 'ΣΣ'));
        $this->assertSame(3, mb_strrpos('ababab', 'b', -2));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strpos
     */
    public function testStrposEmptyDelimiter()
    {
        mb_strpos('abc', 'a');
        $this->setExpectedException('PHPUnit_Framework_Error_Warning', 'Empty delimiter');
        mb_strpos('abc', '');
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strpos
     */
    public function testStrposNegativeOffset()
    {
        mb_strpos('abc', 'a');
        $this->setExpectedException('PHPUnit_Framework_Error_Warning', 'Offset not contained in string');
        mb_strpos('abc', 'a', -1);
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strstr
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_stristr
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strrchr
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strrichr
     */
    public function testStrstr()
    {
        $this->assertSame('국어', mb_strstr('한국어', '국'));
        $this->assertSame('ÉJÀ', mb_stristr('DÉJÀ', 'é'));

        $this->assertSame('éjàdéjà', mb_strstr('déjàdéjà', 'é'));
        $this->assertSame('ÉJÀDÉJÀ', mb_stristr('DÉJÀDÉJÀ', 'é'));
        $this->assertSame('ςσb', mb_stristr('aςσb', 'ΣΣ'));
        $this->assertSame('éjà', mb_strrchr('déjàdéjà', 'é'));
        $this->assertSame('ÉJÀ', mb_strrichr('DÉJÀDÉJÀ', 'é'));

        $this->assertSame('d', mb_strstr('déjàdéjà', 'é', true));
        $this->assertSame('D', mb_stristr('DÉJÀDÉJÀ', 'é', true));
        $this->assertSame('a', mb_stristr('aςσb', 'ΣΣ', true));
        $this->assertSame('déjàd', mb_strrchr('déjàdéjà', 'é', true));
        $this->assertSame('DÉJÀD', mb_strrichr('DÉJÀDÉJÀ', 'é', true));
        $this->assertSame('Paris', mb_stristr('der Straße nach Paris', 'Paris'));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_check_encoding
     */
    public function testCheckEncoding()
    {
        $this->assertFalse(p::mb_check_encoding());
        $this->assertTrue(mb_check_encoding('aςσb', 'UTF8'));
        $this->assertTrue(mb_check_encoding('abc', 'ASCII'));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_detect_encoding
     */
    public function testDetectEncoding()
    {
        $this->assertTrue(mb_detect_order('ASCII, UTF-8'));
        $this->assertSame('ASCII', mb_detect_encoding('abc'));
        $this->assertSame('UTF-8', mb_detect_encoding('abc', 'UTF8, ASCII'));
        $this->assertSame('ISO-8859-1', mb_detect_encoding("\x9D", array('UTF-8', 'ASCII', 'ISO-8859-1')));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_detect_order
     */
    public function testDetectOrder()
    {
        $this->assertTrue(mb_detect_order('ASCII, UTF-8'));
        $this->assertSame(array('ASCII', 'UTF-8'), mb_detect_order());
        $this->assertTrue(mb_detect_order(array('ASCII', 'UTF-8')));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_language
     */
    public function testLanguage()
    {
        $this->assertTrue(mb_language('UNI'));
        $this->assertSame('uni', mb_language());
        $this->assertFalse(@mb_language('ABC'));
        $this->assertTrue(mb_language('neutral'));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_encoding_aliases
     */
    public function testEncodingAliases()
    {
        $this->assertSame(array('utf8'), mb_encoding_aliases('UTF-8'));
        $this->assertFalse(p::mb_encoding_aliases('ASCII'));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Mbstring::mb_strwidth
     */
    public function testStrwidth()
    {
        $this->assertSame(3, mb_strwidth("\000実", 'UTF-8'));
        $this->assertSame(4, mb_strwidth('déjà', 'UTF-8'));
        $this->assertSame(4, mb_strwidth(utf8_decode('déjà'), 'CP1252'));
    }
}
