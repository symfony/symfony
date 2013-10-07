<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Test;

use Symfony\Component\Intl\Exception\NoSuchEntryException;
use Symfony\Component\Intl\Intl;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class LanguageBundleConsistencyTestCase extends ConsistencyTestCase
{
    protected static $localesWithoutTranslationForThemselves = array();
    protected static $localesWithoutTranslationForEnglish = array();
    protected static $localesWithoutTranslationForFrench = array();
    protected static $localesWithoutTranslationForSpanish = array();
    protected static $localesWithoutTranslationForRussian = array();
    protected static $localesWithoutTranslationForChinese = array();
    protected static $localesWithoutTranslationForGerman = array();

    protected static $localesWithoutTranslationForAnyScript = array();
    protected static $localesWithoutTranslationForLatinScript = array();
    protected static $localesWithoutTranslationForSimplifiedScript = array();
    protected static $localesWithoutTranslationForTraditionalScript = array();
    protected static $localesWithoutTranslationForCyrillicScript = array();

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface
     */
    protected static $languageBundle;

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
     * @group locale-alias-based
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
     * @group locale-alias-based
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
     */
    public function testGetScriptNamesAndGetScriptNameAreConsistent($displayLocale)
    {
        $names = static::$languageBundle->getScriptNames($displayLocale);

        foreach ($names as $script => $name) {
            $this->assertSame($name, static::$languageBundle->getScriptName($script, null, $displayLocale));
        }
    }
}
