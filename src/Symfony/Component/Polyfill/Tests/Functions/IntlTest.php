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

use Symfony\Component\Polyfill\Functions\Intl as p;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @covers Symfony\Component\Polyfill\Functions\Intl::<!public>
 */
class IntlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_extract
     */
    public function testGraphemeExtractArrayError()
    {
        grapheme_extract('', 0);
        $this->setExpectedException('PHPUnit_Framework_Error_Warning', 'expects parameter 1 to be string, array given');
        grapheme_extract(array(), 0);
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_extract
     */
    public function testGraphemeExtract()
    {
        $this->assertFalse(grapheme_extract('abc', 1, -1));

        $this->assertSame(grapheme_extract('',    0), grapheme_extract('',    0));
        $this->assertSame(grapheme_extract('abc', 0), grapheme_extract('abc', 0));

        $this->assertSame('국어', grapheme_extract('한국어', 2, GRAPHEME_EXTR_COUNT, 3, $next));
        $this->assertSame(9, $next);

        $next = 0;
        $this->assertSame('한', grapheme_extract('한국어', 1, GRAPHEME_EXTR_COUNT, $next, $next));
        $this->assertSame('국', grapheme_extract('한국어', 1, GRAPHEME_EXTR_COUNT, $next, $next));
        $this->assertSame('어', grapheme_extract('한국어', 1, GRAPHEME_EXTR_COUNT, $next, $next));
        $this->assertFalse(grapheme_extract('한국어', 1, GRAPHEME_EXTR_COUNT, $next, $next));

        $this->assertSame(str_repeat('-', 69000), grapheme_extract(str_repeat('-', 70000), 69000, GRAPHEME_EXTR_COUNT));

        $this->assertSame('d', grapheme_extract('déjà', 2, GRAPHEME_EXTR_MAXBYTES));
        $this->assertSame('dé', grapheme_extract('déjà', 2, GRAPHEME_EXTR_MAXCHARS));

        $this->assertFalse(@grapheme_extract(array(), 0));
        $this->assertFalse(@grapheme_extract(array(), 0));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_strlen
     */
    public function testGraphemeStrlen()
    {
        $this->assertSame(3, grapheme_strlen('한국어'));
        $this->assertSame(3, grapheme_strlen(\Normalizer::normalize('한국어', \Normalizer::NFD)));

        $this->assertNull(grapheme_strlen("\xE9"));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_substr
     */
    public function testGraphemeSubstr()
    {
        $c = 'déjà';

        if (PHP_VERSION_ID >= 50418 && PHP_VERSION_ID !== 50500) {
            // See http://bugs.php.net/62759 and 55562
            $this->assertSame( 'jà', grapheme_substr($c, -2,  3) );
            $this->assertSame( '', grapheme_substr($c, -1,  0) );
            $this->assertSame( false, grapheme_substr($c,  1, -4) );
        }

        $this->assertSame('jà', grapheme_substr($c,  2));
        $this->assertSame('jà', grapheme_substr($c, -2));
        $this->assertSame('j', grapheme_substr($c, -2, -1));
        $this->assertSame('', grapheme_substr($c, -2, -2));
        $this->assertSame(false, grapheme_substr($c,  5,  0));
        $this->assertSame(false, grapheme_substr($c, -5,  0));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_strpos
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_stripos
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_strrpos
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_strripos
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_position
     */
    public function testGraphemeStrpos()
    {
        $this->assertSame(false, grapheme_strpos('abc', ''));
        $this->assertSame(false, grapheme_strpos('abc', 'd'));
        $this->assertSame(false, grapheme_strpos('abc', 'a', 3));
        $this->assertSame(0, grapheme_strpos('abc', 'a', -1));
        $this->assertSame(1, grapheme_strpos('한국어', '국'));
        $this->assertSame(3, grapheme_stripos('DÉJÀ', 'à'));
        $this->assertSame(false, grapheme_strrpos('한국어', ''));
        $this->assertSame(1, grapheme_strrpos('한국어', '국'));
        $this->assertSame(3, grapheme_strripos('DÉJÀ', 'à'));
    }

    /**
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_strstr
     * @covers Symfony\Component\Polyfill\Functions\Intl::grapheme_stristr
     */
    public function testGraphemeStrstr()
    {
        $this->assertSame('국어', grapheme_strstr('한국어', '국'));
        $this->assertSame('ÉJÀ', grapheme_stristr('DÉJÀ', 'é'));
    }

    public function testGraphemeBugs()
    {
        if (PHP_VERSION_ID < 50418 || PHP_VERSION_ID === 50500) {
            // see https://bugs.php.net/61860
            $this->markTestSkipped('PHP 5.4.18 or 5.5.1 is required.');
        }
        $this->assertSame(16, grapheme_stripos('der Straße nach Paris', 'Paris'));
        $this->assertSame('Paris', grapheme_stristr('der Straße nach Paris', 'Paris'));
    }
}
