<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Test\ConsistencyTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleBundleConsistencyTest extends ConsistencyTest
{
    // The below arrays document the current state of the ICU data.
    // This state is verified in the tests below.
    // You can add arbitrary rules here if you want to document the availability
    // of other locale names.
    private static $localesWithoutTranslationForThemselves = array('nmg');
    private static $localesWithoutTranslationForEnglish = array('as', 'bo', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'uz');
    private static $localesWithoutTranslationForFrench = array('as', 'bo', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'uz');
    private static $localesWithoutTranslationForSpanish = array('as', 'bo', 'dua', 'fo', 'gv', 'jgo', 'kl', 'kw', 'lo', 'mgo', 'ps', 'uz');
    private static $localesWithoutTranslationForRussian = array('as', 'dua', 'fo', 'gv', 'jgo', 'kl', 'kw', 'mgo', 'pa', 'uz');
    private static $localesWithoutTranslationForChinese = array('as', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'pa', 'rw', 'ti', 'uz');
    private static $localesWithoutTranslationForGerman = array('as', 'bo', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'uz');

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface
     */
    private static $localeBundle;

    public static function setUpBeforeClass()
    {
        static::$localeBundle = Intl::getLocaleBundle();
    }

    /**
     * @dataProvider provideRootLocales
     */
    public function testGetLocaleNames($displayLocale)
    {
        $locales = static::$localeBundle->getLocaleNames($displayLocale);

        if (in_array($displayLocale, static::$localesWithoutTranslationForThemselves)) {
            $this->assertArrayNotHasKey($displayLocale, $locales);
        } else {
            $this->assertArrayHasKey($displayLocale, $locales);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForEnglish)) {
            $this->assertArrayNotHasKey('en', $locales);
        } else {
            $this->assertArrayHasKey('en', $locales);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForFrench)) {
            $this->assertArrayNotHasKey('fr', $locales);
        } else {
            $this->assertArrayHasKey('fr', $locales);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForSpanish)) {
            $this->assertArrayNotHasKey('es', $locales);
        } else {
            $this->assertArrayHasKey('es', $locales);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForRussian)) {
            $this->assertArrayNotHasKey('ru', $locales);
        } else {
            $this->assertArrayHasKey('ru', $locales);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForChinese)) {
            $this->assertArrayNotHasKey('zh', $locales);
        } else {
            $this->assertArrayHasKey('zh', $locales);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForGerman)) {
            $this->assertArrayNotHasKey('de', $locales);
        } else {
            $this->assertArrayHasKey('de', $locales);
        }
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetLocaleNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            static::$localeBundle->getLocaleNames($ofLocale),
            static::$localeBundle->getLocaleNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     * @group icu-consistency
     */
    public function testGetLocaleNamesAndGetLocaleNameAreConsistent($displayLocale)
    {
        $names = static::$localeBundle->getLocaleNames($displayLocale);

        foreach ($names as $locale => $name) {
            $this->assertSame($name, static::$localeBundle->getLocaleName($locale, $displayLocale));
        }
    }
}
