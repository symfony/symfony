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

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locale;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class LanguageBundleConsistencyTestCase extends ConsistencyTestCase
{
    protected static $localesWithoutTranslationForAnyLanguage = array();
    protected static $localesWithoutTranslationForThemselves = array();
    protected static $localesWithoutTranslationForLanguage = array();

    protected static $localesWithoutTranslationForAnyScript = array();
    protected static $localesWithoutTranslationForScript = array();

    public function provideLocalesWithScripts()
    {
        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyScript);
        $aliasesWithoutScripts = array_intersect_assoc($this->getLocaleAliases(), static::$localesWithoutTranslationForAnyScript);

        // remove locales that have no "Scripts" block or are an alias to a locale
        // without "Scripts" block
        $locales = array_filter($this->getLocales(), function ($locale) use ($localesWithoutScript, $aliasesWithoutScripts) {
            while (null !== $locale) {
                if (isset($localesWithoutScript[$locale]) || isset($aliasesWithoutScripts[$locale])) {
                    return false;
                }

                $locale = Locale::getFallback($locale);
            }

            return true;
        });

        return array_map(
            function ($locale) { return array($locale); },
            $locales
        );
    }

    public function provideLocaleAliasesWithScripts()
    {
        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyScript);

        // Remove aliases that point to a locale without "Scripts" block
        $aliases = array_filter($this->getLocaleAliases(), function ($targetLocale) use ($localesWithoutScript) {
            while (null !== $targetLocale) {
                if (isset($localesWithoutScript[$targetLocale])) {
                    return false;
                }

                $targetLocale = Locale::getFallback($targetLocale);
            }

            return true;
        });

        return array_map(
            function ($alias, $ofLocale) { return array($alias, $ofLocale); },
            array_keys($aliases),
            $aliases
        );
    }

    public function testGetLanguageNames()
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getLanguageBundle()->getLanguageNames($displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (MissingResourceException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForAnyLanguage, $untranslatedLocales);
    }

    public function provideTestedLanguages()
    {
        return array_map(
            function ($language) { return array($language); },
            array_keys(static::$localesWithoutTranslationForLanguage)
        );
    }

    /**
     * @dataProvider provideTestedLanguages
     */
    public function testGetLanguageName($language)
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getLanguageBundle()->getLanguageName($language ?: $displayLocale, null, $displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (MissingResourceException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, static::$localesWithoutTranslationForAnyLanguage, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForLanguage[$language], $untranslatedLocales);
    }

    public function testGetScriptNames()
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getLanguageBundle()->getScriptNames($displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (MissingResourceException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForAnyScript, $untranslatedLocales);
    }

    public function provideTestedScripts()
    {
        return array_map(
            function ($script) { return array($script); },
            array_keys(static::$localesWithoutTranslationForScript)
        );
    }

    /**
     * @dataProvider provideTestedScripts
     */
    public function testGetScriptName($script)
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getLanguageBundle()->getScriptName($script, null, $displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (MissingResourceException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, static::$localesWithoutTranslationForAnyScript, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForScript[$script], $untranslatedLocales);
    }

    /**
     * @dataProvider provideLocaleAliases
     * @group locale-alias-based
     */
    public function testGetLanguageNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            Intl::getLanguageBundle()->getLanguageNames($ofLocale),
            Intl::getLanguageBundle()->getLanguageNames($alias)
        );
    }

    /**
     * @dataProvider provideLocaleAliasesWithScripts
     * @group locale-alias-based
     */
    public function testGetScriptNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            Intl::getLanguageBundle()->getScriptNames($ofLocale),
            Intl::getLanguageBundle()->getScriptNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetLanguageNamesAndGetLanguageNameAreConsistent($displayLocale)
    {
        $names = Intl::getLanguageBundle()->getLanguageNames($displayLocale);

        foreach ($names as $language => $name) {
            $this->assertSame($name, Intl::getLanguageBundle()->getLanguageName($language, null, $displayLocale));
        }
    }

    /**
     * @dataProvider provideLocalesWithScripts
     */
    public function testGetScriptNamesAndGetScriptNameAreConsistent($displayLocale)
    {
        $names = Intl::getLanguageBundle()->getScriptNames($displayLocale);

        foreach ($names as $script => $name) {
            $this->assertSame($name, Intl::getLanguageBundle()->getScriptName($script, null, $displayLocale));
        }
    }
}
