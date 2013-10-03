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

use Symfony\Component\Intl\Exception\NoSuchEntryException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Test\ConsistencyTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LanguageBundleConsistencyTest extends ConsistencyTest
{
    // The below arrays document the current state of the ICU data.
    // This state is verified in the tests below.
    // You can add arbitrary rules here if you want to document the availability
    // of other languages.
    private static $localesWithoutTranslationForThemselves = array('nmg');
    private static $localesWithoutTranslationForEnglish = array('as', 'bo', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'uz');
    private static $localesWithoutTranslationForFrench = array('as', 'bo', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'uz');
    private static $localesWithoutTranslationForSpanish = array('as', 'bo', 'dua', 'fo', 'gv', 'jgo', 'kl', 'kw', 'lo', 'mgo', 'ps', 'uz');
    private static $localesWithoutTranslationForRussian = array('as', 'dua', 'fo', 'gv', 'jgo', 'kl', 'kw', 'mgo', 'pa', 'uz');
    private static $localesWithoutTranslationForChinese = array('as', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'pa', 'rw', 'ti', 'uz');
    private static $localesWithoutTranslationForGerman = array('as', 'bo', 'dua', 'fo', 'gv', 'kl', 'kw', 'mgo', 'uz');

    private static $localesWithoutTranslationForAnyScript = array(
        'agq', 'ak', 'asa', 'bas', 'bem', 'bez', 'bm', 'cgg', 'dav', 'dje', 'dua',
        'dyo', 'ebu', 'eo', 'ewo', 'ff', 'fo', 'ga', 'guz', 'gv', 'ha', 'haw',
        'ig', 'jmc', 'kab', 'kam', 'kde', 'khq', 'ki', 'kl', 'kln', 'kok', 'ksb',
        'ksf', 'kw', 'lag', 'lg', 'ln', 'lu', 'luo', 'luy', 'mas', 'mer', 'mfe',
        'mg', 'mgh', 'mua', 'naq', 'nd', 'nmg', 'nus', 'nyn', 'pa', 'rn', 'rof',
        'rw', 'rwk', 'saq', 'sbp', 'seh', 'ses', 'sg', 'shi', 'sn', 'swc', 'teo',
        'twq', 'tzm', 'uz', 'vai', 'vun', 'xog', 'yav', 'yo'
    );

    private static $localesWithoutTranslationForLatinScript = array('as', 'bo', 'lo', 'ps', 'so');
    private static $localesWithoutTranslationForSimplifiedScript = array('as', 'bo', 'jgo', 'mgo', 'om', 'ps', 'so', 'sq', 'ti');
    private static $localesWithoutTranslationForTraditionalScript = array('as', 'bo', 'jgo', 'mgo', 'om', 'ps', 'so', 'sq', 'ti');
    private static $localesWithoutTranslationForCyrillicScript = array('as', 'bo', 'jgo', 'lo', 'mgo', 'om', 'ps', 'so', 'mt', 'sq', 'ti');

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface
     */
    private static $languageBundle;

    public static function setUpBeforeClass()
    {
        static::$languageBundle = Intl::getLanguageBundle();
    }

    public function provideLocalesWithScripts()
    {
        $parameters = array();

        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyScript);
        $aliasesWithoutScripts = Intl::getLocaleBundle()->getLocaleAliases();
        $aliasesWithoutScripts = array_intersect_assoc($aliasesWithoutScripts, static::$localesWithoutTranslationForAnyScript);

        $locales = Intl::getLocaleBundle()->getLocales();

        // remove locales that have no "Scripts" block or are an alias to a locale
        // without "Scripts" block
        $locales = array_filter($locales, function ($locale) use ($localesWithoutScript, $aliasesWithoutScripts) {
            while (null !== $locale) {
                if (isset($localesWithoutScript[$locale]) || isset($aliasesWithoutScripts[$locale])) {
                    return false;
                }

                $locale = Intl::getFallbackLocale($locale);
            }

            return true;
        });

        foreach ($locales as $locale) {
            $parameters[] = array($locale);
        }

        return $parameters;
    }

    public function provideLocaleAliasesWithScripts()
    {
        $parameters = array();

        $aliases = Intl::getLocaleBundle()->getLocaleAliases();
        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyScript);

        // Remove aliases that point to a locale without "Scripts" block
        $aliases = array_filter($aliases, function ($targetLocale) use ($localesWithoutScript) {
            while (null !== $targetLocale) {
                if (isset($localesWithoutScript[$targetLocale])) {
                    return false;
                }

                $targetLocale = Intl::getFallbackLocale($targetLocale);
            }

            return true;
        });

        foreach ($aliases as $alias => $ofLocale) {
            $parameters[] = array($alias, $ofLocale);
        }

        return $parameters;
    }

    /**
     * @dataProvider provideRootLocales
     */
    public function testGetLanguageNames($displayLocale)
    {
        $languageNames = static::$languageBundle->getLanguageNames($displayLocale);

        if (in_array($displayLocale, static::$localesWithoutTranslationForThemselves)) {
            $this->assertArrayNotHasKey($displayLocale, $languageNames);
        } else {
            $this->assertArrayHasKey($displayLocale, $languageNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForEnglish)) {
            $this->assertArrayNotHasKey('en', $languageNames);
        } else {
            $this->assertArrayHasKey('en', $languageNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForFrench)) {
            $this->assertArrayNotHasKey('fr', $languageNames);
        } else {
            $this->assertArrayHasKey('fr', $languageNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForSpanish)) {
            $this->assertArrayNotHasKey('es', $languageNames);
        } else {
            $this->assertArrayHasKey('es', $languageNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForRussian)) {
            $this->assertArrayNotHasKey('ru', $languageNames);
        } else {
            $this->assertArrayHasKey('ru', $languageNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForChinese)) {
            $this->assertArrayNotHasKey('zh', $languageNames);
        } else {
            $this->assertArrayHasKey('zh', $languageNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForGerman)) {
            $this->assertArrayNotHasKey('de', $languageNames);
        } else {
            $this->assertArrayHasKey('de', $languageNames);
        }
    }

    /**
     * @dataProvider provideRootLocales
     */
    public function testGetScriptNames($displayLocale)
    {
        try {
            $scriptNames = static::$languageBundle->getScriptNames($displayLocale);

            if (in_array($displayLocale, static::$localesWithoutTranslationForAnyScript)) {
                $this->fail('Did not expect any script translations for locale '.$displayLocale);
            }
        } catch (NoSuchEntryException $e) {
            if (in_array($displayLocale, static::$localesWithoutTranslationForAnyScript)) {
                return;
            }

            throw $e;
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForLatinScript)) {
            $this->assertArrayNotHasKey('Latn', $scriptNames);
        } else {
            $this->assertArrayHasKey('Latn', $scriptNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForSimplifiedScript)) {
            $this->assertArrayNotHasKey('Hans', $scriptNames);
        } else {
            $this->assertArrayHasKey('Hans', $scriptNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForTraditionalScript)) {
            $this->assertArrayNotHasKey('Hant', $scriptNames);
        } else {
            $this->assertArrayHasKey('Hant', $scriptNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForCyrillicScript)) {
            $this->assertArrayNotHasKey('Cyrl', $scriptNames);
        } else {
            $this->assertArrayHasKey('Cyrl', $scriptNames);
        }
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetLanguageNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            static::$languageBundle->getLanguageNames($ofLocale),
            static::$languageBundle->getLanguageNames($alias)
        );
    }

    /**
     * @dataProvider provideLocaleAliasesWithScripts
     */
    public function testGetScriptNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            static::$languageBundle->getScriptNames($ofLocale),
            static::$languageBundle->getScriptNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     * @group icu-consistency
     */
    public function testGetLanguageNamesAndGetLanguageNameAreConsistent($displayLocale)
    {
        $names = static::$languageBundle->getLanguageNames($displayLocale);

        foreach ($names as $language => $name) {
            $this->assertSame($name, static::$languageBundle->getLanguageName($language, null, $displayLocale));
        }
    }

    /**
     * @dataProvider provideLocalesWithScripts
     * @group icu-consistency
     */
    public function testGetScriptNamesAndGetScriptNameAreConsistent($displayLocale)
    {
        $names = static::$languageBundle->getScriptNames($displayLocale);

        foreach ($names as $script => $name) {
            $this->assertSame($name, static::$languageBundle->getScriptName($script, null, $displayLocale));
        }
    }
}
