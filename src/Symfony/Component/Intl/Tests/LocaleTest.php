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
        $tests = array(
            array('sl_Latn_IT', 'sl_Latn_IT_nedis'),
            array('sl_Latn', 'sl_Latn_IT'),
            array('fr', 'fr_FR'),
            array('fr', 'fr-FR'),
            array('en', 'fr'),
            array('root', 'en'),
            array(null, 'root'),
        );

        if (\function_exists('locale_parse')) {
            $tests[] = array('sl_Latn_IT', 'sl-Latn-IT-nedis');
            $tests[] = array('sl_Latn', 'sl-Latn_IT');
        } else {
            $tests[] = array('sl-Latn-IT', 'sl-Latn-IT-nedis');
            $tests[] = array('sl-Latn', 'sl-Latn-IT');
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
}
