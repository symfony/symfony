<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Encoder\Rfc2231Encoder;

class Rfc2231EncoderTest extends TestCase
{
    private $rfc2045Token = '/^[\x21\x23-\x27\x2A\x2B\x2D\x2E\x30-\x39\x41-\x5A\x5E-\x7E]+$/D';

    /* --
    This algorithm is described in RFC 2231, but is barely touched upon except
    for mentioning bytes can be represented as their octet values (e.g. %20 for
    the SPACE character).

    The tests here focus on how to use that representation to always generate text
    which matches RFC 2045's definition of "token".
    */

    public function testEncodingAsciiCharactersProducesValidToken()
    {
        $string = '';
        foreach (range(0x00, 0x7F) as $octet) {
            $char = pack('C', $octet);
            $string .= $char;
        }
        $encoder = new Rfc2231Encoder();
        $encoded = $encoder->encodeString($string);

        foreach (explode("\r\n", $encoded) as $line) {
            $this->assertMatchesRegularExpression($this->rfc2045Token, $line, 'Encoder should always return a valid RFC 2045 token.');
        }
    }

    public function testEncodingNonAsciiCharactersProducesValidToken()
    {
        $string = '';
        foreach (range(0x80, 0xFF) as $octet) {
            $char = pack('C', $octet);
            $string .= $char;
        }
        $encoder = new Rfc2231Encoder();
        $encoded = $encoder->encodeString($string);

        foreach (explode("\r\n", $encoded) as $line) {
            $this->assertMatchesRegularExpression($this->rfc2045Token, $line, 'Encoder should always return a valid RFC 2045 token.');
        }
    }

    public function testMaximumLineLengthCanBeSet()
    {
        $string = '';
        for ($x = 0; $x < 200; ++$x) {
            $char = 'a';
            $string .= $char;
        }
        $encoder = new Rfc2231Encoder();
        $encoded = $encoder->encodeString($string, 'utf-8', 0, 75);

        // 72 here and not 75 as we read 4 chars at a time
        $this->assertEquals(
            str_repeat('a', 72)."\r\n".
            str_repeat('a', 72)."\r\n".
            str_repeat('a', 56),
            $encoded,
            'Lines should be wrapped at each 72 characters'
        );
    }

    public function testFirstLineCanHaveShorterLength()
    {
        $string = '';
        for ($x = 0; $x < 200; ++$x) {
            $char = 'a';
            $string .= $char;
        }
        $encoder = new Rfc2231Encoder();
        $encoded = $encoder->encodeString($string, 'utf-8', 24, 72);

        $this->assertEquals(
            str_repeat('a', 48)."\r\n".
            str_repeat('a', 72)."\r\n".
            str_repeat('a', 72)."\r\n".
            str_repeat('a', 8),
            $encoded,
            'First line should be 24 bytes shorter than the others.'
        );
    }

    public function testEncodingAndDecodingSamples()
    {
        $dir = realpath(__DIR__.'/../Fixtures/samples/charsets');
        $sampleFp = opendir($dir);
        while (false !== $encoding = readdir($sampleFp)) {
            if (str_starts_with($encoding, '.')) {
                continue;
            }

            $encoder = new Rfc2231Encoder();
            if (is_dir($dir.'/'.$encoding)) {
                $fileFp = opendir($dir.'/'.$encoding);
                while (false !== $sampleFile = readdir($fileFp)) {
                    if (str_starts_with($sampleFile, '.')) {
                        continue;
                    }

                    $text = file_get_contents($dir.'/'.$encoding.'/'.$sampleFile);
                    $encodedText = $encoder->encodeString($text, $encoding);
                    $this->assertEquals(
                        urldecode(implode('', explode("\r\n", $encodedText))), $text,
                        'Encoded string should decode back to original string for sample '.$dir.'/'.$encoding.'/'.$sampleFile
                    );
                }
                closedir($fileFp);
            }
        }
        closedir($sampleFp);
    }
}
