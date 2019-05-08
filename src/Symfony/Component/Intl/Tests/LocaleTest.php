<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Locale;

class LocaleTest extends TestCase
{
    public function provideGetFallbackTests()
    {
        $tests = [
            ['sl_Latn_IT', 'sl_Latn_IT_nedis'],
            ['sl_Latn', 'sl_Latn_IT'],
            ['fr', 'fr_FR'],
            ['fr', 'fr-FR'],
            ['en', 'fr'],
            ['root', 'en'],
            [null, 'root'],
        ];

        if (\function_exists('locale_parse')) {
            $tests[] = ['sl_Latn_IT', 'sl-Latn-IT-nedis'];
            $tests[] = ['sl_Latn', 'sl-Latn_IT'];
        } else {
            $tests[] = ['sl-Latn-IT', 'sl-Latn-IT-nedis'];
            $tests[] = ['sl-Latn', 'sl-Latn-IT'];
        }

        return $tests;
    }

    /**
     * @dataProvider provideGetFallbackTests
     */
    public function testGetFallback($expected, $locale)
    {
        $this->assertSame($expected, Locale::getFallback($locale));
    }

    public function testNoDefaultFallback()
    {
        $prev = Locale::getDefaultFallback();
        Locale::setDefaultFallback(null);

        $this->assertSame('nl', Locale::getFallback('nl_NL'));
        $this->assertNull(Locale::getFallback('nl'));
        $this->assertNull(Locale::getFallback('root'));

        Locale::setDefaultFallback($prev);
    }

    public function testDefaultRootFallback()
    {
        $prev = Locale::getDefaultFallback();
        Locale::setDefaultFallback('root');

        $this->assertSame('nl', Locale::getFallback('nl_NL'));
        $this->assertSame('root', Locale::getFallback('nl'));
        $this->assertNull(Locale::getFallback('root'));

        Locale::setDefaultFallback($prev);
    }
}
