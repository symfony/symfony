<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Header;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\ParameterizedHeader;

class ParameterizedHeaderTest extends TestCase
{
    private $charset = 'utf-8';
    private $lang = 'en-us';

    public function testValueIsReturnedVerbatim()
    {
        $header = new ParameterizedHeader('Content-Type', 'text/plain');
        $this->assertEquals('text/plain', $header->getValue());
    }

    public function testParametersAreAppended()
    {
        /* -- RFC 2045, 5.1
        parameter := attribute "=" value

        attribute := token
                                    ; Matching of attributes
                                    ; is ALWAYS case-insensitive.

        value := token / quoted-string

        token := 1*<any (US-ASCII) CHAR except SPACE, CTLs,
                 or tspecials>

        tspecials :=  "(" / ")" / "<" / ">" / "@" /
                   "," / ";" / ":" / "\" / <">
                   "/" / "[" / "]" / "?" / "="
                   ; Must be in quoted-string,
                   ; to use within parameter values
        */

        $header = new ParameterizedHeader('Content-Type', 'text/plain');
        $header->setParameters(['charset' => 'utf-8']);
        $this->assertEquals('text/plain; charset=utf-8', $header->getBodyAsString());
    }

    public function testSpaceInParamResultsInQuotedString()
    {
        $header = new ParameterizedHeader('Content-Type', 'attachment');
        $header->setParameters(['filename' => 'my file.txt']);
        $this->assertEquals('attachment; filename="my file.txt"', $header->getBodyAsString());
    }

    public function testFormDataResultsInQuotedString()
    {
        $header = new ParameterizedHeader('Content-Disposition', 'form-data');
        $header->setParameters(['filename' => 'file.txt']);
        $this->assertEquals('form-data; filename="file.txt"', $header->getBodyAsString());
    }

    public function testFormDataUtf8()
    {
        $header = new ParameterizedHeader('Content-Disposition', 'form-data');
        $header->setParameters(['filename' => "déjà%\"\n\r.txt"]);
        $this->assertEquals('form-data; filename="déjà%%22%0A%0D.txt"', $header->getBodyAsString());
    }

    public function testLongParamsAreBrokenIntoMultipleAttributeStrings()
    {
        /* -- RFC 2231, 3.
        The asterisk character ("*") followed
        by a decimal count is employed to indicate that multiple parameters
        are being used to encapsulate a single parameter value.  The count
        starts at 0 and increments by 1 for each subsequent section of the
        parameter value.  Decimal values are used and neither leading zeroes
        nor gaps in the sequence are allowed.

        The original parameter value is recovered by concatenating the
        various sections of the parameter, in order.  For example, the
        content-type field

                Content-Type: message/external-body; access-type=URL;
         URL*0="ftp://";
         URL*1="cs.utk.edu/pub/moore/bulk-mailer/bulk-mailer.tar"

        is semantically identical to

                Content-Type: message/external-body; access-type=URL;
                    URL="ftp://cs.utk.edu/pub/moore/bulk-mailer/bulk-mailer.tar"

        Note that quotes around parameter values are part of the value
        syntax; they are NOT part of the value itself.  Furthermore, it is
        explicitly permitted to have a mixture of quoted and unquoted
        continuation fields.
        */

        $value = str_repeat('a', 180);

        $header = new ParameterizedHeader('Content-Disposition', 'attachment');
        $header->setParameters(['filename' => $value]);
        $this->assertEquals(
            'attachment; '.
            'filename*0*=utf-8\'\''.str_repeat('a', 60).";\r\n ".
            'filename*1*='.str_repeat('a', 60).";\r\n ".
            'filename*2*='.str_repeat('a', 60),
            $header->getBodyAsString()
        );
    }

    public function testEncodedParamDataIncludesCharsetAndLanguage()
    {
        /* -- RFC 2231, 4.
        Asterisks ("*") are reused to provide the indicator that language and
        character set information is present and encoding is being used. A
        single quote ("'") is used to delimit the character set and language
        information at the beginning of the parameter value. Percent signs
        ("%") are used as the encoding flag, which agrees with RFC 2047.

        Specifically, an asterisk at the end of a parameter name acts as an
        indicator that character set and language information may appear at
        the beginning of the parameter value. A single quote is used to
        separate the character set, language, and actual value information in
        the parameter value string, and an percent sign is used to flag
        octets encoded in hexadecimal.  For example:

                Content-Type: application/x-stuff;
         title*=us-ascii'en-us'This%20is%20%2A%2A%2Afun%2A%2A%2A

        Note that it is perfectly permissible to leave either the character
        set or language field blank.  Note also that the single quote
        delimiters MUST be present even when one of the field values is
        omitted.
        */

        $value = str_repeat('a', 20).pack('C', 0x8F).str_repeat('a', 10);
        $header = new ParameterizedHeader('Content-Disposition', 'attachment');
        $header->setCharset('iso-8859-1');
        $header->setValue('attachment');
        $header->setParameters(['filename' => $value]);
        $header->setLanguage($this->lang);
        $this->assertEquals(
            'attachment; filename*='.$header->getCharset()."'".$this->lang."'".
            str_repeat('a', 20).'%8F'.str_repeat('a', 10),
            $header->getBodyAsString()
        );
    }

    public function testMultipleEncodedParamLinesAreFormattedCorrectly()
    {
        /* -- RFC 2231, 4.1.
        Character set and language information may be combined with the
        parameter continuation mechanism. For example:

        Content-Type: application/x-stuff
        title*0*=us-ascii'en'This%20is%20even%20more%20
        title*1*=%2A%2A%2Afun%2A%2A%2A%20
        title*2="isn't it!"

           Note that:

        (1)   Language and character set information only appear at
              the beginning of a given parameter value.

        (2)   Continuations do not provide a facility for using more
              than one character set or language in the same
              parameter value.

        (3)   A value presented using multiple continuations may
              contain a mixture of encoded and unencoded segments.

        (4)   The first segment of a continuation MUST be encoded if
              language and character set information are given.

        (5)   If the first segment of a continued parameter value is
              encoded the language and character set field delimiters
              MUST be present even when the fields are left blank.
           */

        $value = str_repeat('a', 20).pack('C', 0x8F).str_repeat('a', 60);
        $header = new ParameterizedHeader('Content-Disposition', 'attachment');
        $header->setValue('attachment');
        $header->setCharset('utf-6');
        $header->setParameters(['filename' => $value]);
        $header->setLanguage($this->lang);
        $this->assertEquals(
            'attachment; filename*0*='.$header->getCharset()."'".$this->lang."'".
            str_repeat('a', 20).'%8F'.str_repeat('a', 23).";\r\n ".
            'filename*1*='.str_repeat('a', 37),
            $header->getBodyAsString()
        );
    }

    public function testToString()
    {
        $header = new ParameterizedHeader('Content-Type', 'text/html');
        $header->setParameters(['charset' => 'utf-8']);
        $this->assertEquals('Content-Type: text/html; charset=utf-8', $header->toString());
    }

    public function testValueCanBeEncodedIfNonAscii()
    {
        $value = 'fo'.pack('C', 0x8F).'bar';
        $header = new ParameterizedHeader('X-Foo', $value);
        $header->setCharset('iso-8859-1');
        $header->setParameters(['lookslike' => 'foobar']);
        $this->assertEquals('X-Foo: =?'.$header->getCharset().'?Q?fo=8Fbar?=; lookslike=foobar', $header->toString());
    }

    public function testValueAndParamCanBeEncodedIfNonAscii()
    {
        $value = 'fo'.pack('C', 0x8F).'bar';
        $header = new ParameterizedHeader('X-Foo', $value);
        $header->setCharset('iso-8859-1');
        $header->setParameters(['says' => $value]);
        $this->assertEquals('X-Foo: =?'.$header->getCharset().'?Q?fo=8Fbar?=; says*='.$header->getCharset()."''fo%8Fbar", $header->toString());
    }

    public function testParamsAreEncodedIfNonAscii()
    {
        $value = 'fo'.pack('C', 0x8F).'bar';
        $header = new ParameterizedHeader('X-Foo', 'bar');
        $header->setCharset('iso-8859-1');
        $header->setParameters(['says' => $value]);
        $this->assertEquals('X-Foo: bar; says*='.$header->getCharset()."''fo%8Fbar", $header->toString());
    }

    public function testParamsAreEncodedWithLegacyEncodingEnabled()
    {
        $value = 'fo'.pack('C', 0x8F).'bar';
        $header = new ParameterizedHeader('Content-Type', 'bar');
        $header->setCharset('iso-8859-1');
        $header->setParameters(['says' => $value]);
        $this->assertEquals('Content-Type: bar; says="=?'.$header->getCharset().'?Q?fo=8Fbar?="', $header->toString());
    }

    public function testLanguageInformationAppearsInEncodedWords()
    {
        /* -- RFC 2231, 5.
        5.  Language specification in Encoded Words

        RFC 2047 provides support for non-US-ASCII character sets in RFC 822
        message header comments, phrases, and any unstructured text field.
        This is done by defining an encoded word construct which can appear
        in any of these places.  Given that these are fields intended for
        display, it is sometimes necessary to associate language information
        with encoded words as well as just the character set.  This
        specification extends the definition of an encoded word to allow the
        inclusion of such information.  This is simply done by suffixing the
        character set specification with an asterisk followed by the language
        tag.  For example:

                    From: =?US-ASCII*EN?Q?Keith_Moore?= <moore@cs.utk.edu>

        -- RFC 2047, 5. Use of encoded-words in message headers
          ...
        + An 'encoded-word' MUST NOT be used in parameter of a MIME
          Content-Type or Content-Disposition field, or in any structured
          field body except within a 'comment' or 'phrase'.

        -- RFC 2047, Appendix - changes since RFC 1522
          ...
        + clarify that encoded-words are allowed in '*text' fields in both
          RFC822 headers and MIME body part headers, but NOT as parameter
          values.
        */

        $value = 'fo'.pack('C', 0x8F).'bar';
        $header = new ParameterizedHeader('X-Foo', $value);
        $header->setCharset('iso-8859-1');
        $header->setLanguage('en');
        $header->setParameters(['says' => $value]);
        $this->assertEquals('X-Foo: =?'.$header->getCharset().'*en?Q?fo=8Fbar?=; says*='.$header->getCharset()."'en'fo%8Fbar", $header->toString());
    }

    public function testSetBody()
    {
        $header = new ParameterizedHeader('Content-Type', 'text/html');
        $header->setBody('text/plain');
        $this->assertEquals('text/plain', $header->getValue());
    }

    public function testGetBody()
    {
        $header = new ParameterizedHeader('Content-Type', 'text/plain');
        $this->assertEquals('text/plain', $header->getBody());
    }

    public function testSetParameter()
    {
        $header = new ParameterizedHeader('Content-Type', 'text/html');
        $header->setParameters(['charset' => 'utf-8', 'delsp' => 'yes']);
        $header->setParameter('delsp', 'no');
        $this->assertEquals(['charset' => 'utf-8', 'delsp' => 'no'], $header->getParameters());
    }

    public function testGetParameter()
    {
        $header = new ParameterizedHeader('Content-Type', 'text/html');
        $header->setParameters(['charset' => 'utf-8', 'delsp' => 'yes']);
        $this->assertEquals('utf-8', $header->getParameter('charset'));
    }
}
