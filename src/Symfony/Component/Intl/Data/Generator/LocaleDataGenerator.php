<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Data\Provider\LanguageDataProvider;
use Symfony\Component\Intl\Data\Provider\RegionDataProvider;
use Symfony\Component\Intl\Data\Provider\ScriptDataProvider;
use Symfony\Component\Intl\Data\Util\LocaleScanner;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Locale;

/**
 * The rule for compiling the locale bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LocaleDataGenerator
{
    private $dirName;
    private $languageDataProvider;
    private $scriptDataProvider;
    private $regionDataProvider;

    public function __construct(string $dirName, LanguageDataProvider $languageDataProvider, ScriptDataProvider $scriptDataProvider, RegionDataProvider $regionDataProvider)
    {
        $this->dirName = $dirName;
        $this->languageDataProvider = $languageDataProvider;
        $this->scriptDataProvider = $scriptDataProvider;
        $this->regionDataProvider = $regionDataProvider;
    }

    public function generateData(GeneratorConfig $config)
    {
        $filesystem = new Filesystem();
        $localeScanner = new LocaleScanner();

        $writers = $config->getBundleWriters();

        // Prepare filesystem directories
        foreach ($writers as $targetDir => $writer) {
            $filesystem->remove($targetDir.'/'.$this->dirName);
            $filesystem->mkdir($targetDir.'/'.$this->dirName);
        }

        $locales = $localeScanner->scanLocales($config->getSourceDir().'/locales');
        $aliases = $localeScanner->scanAliases($config->getSourceDir().'/locales');

        // Flip to facilitate lookup
        $flippedLocales = array_flip($locales);

        // Don't generate names for aliases (names will be generated for the
        // locale they are duplicating)
        $displayLocales = array_diff_key($flippedLocales, $aliases);

        ksort($displayLocales);

        // Generate a list of (existing) locale fallbacks
        $fallbackMapping = $this->generateFallbackMapping($displayLocales, $aliases);

        $localeNames = array();

        // Generate locale names for all locales that have translations in
        // at least the language or the region bundle
        foreach ($displayLocales as $displayLocale => $_) {
            $localeNames[$displayLocale] = array();

            foreach ($locales as $locale) {
                try {
                    // Generate a locale name in the language of each display locale
                    // Each locale name has the form: "Language (Script, Region, Variant1, ...)
                    // Script, Region and Variants are optional. If none of them is
                    // available, the braces are not printed.
                    if (null !== ($name = $this->generateLocaleName($locale, $displayLocale))) {
                        $localeNames[$displayLocale][$locale] = $name;
                    }
                } catch (MissingResourceException $e) {
                } catch (ResourceBundleNotFoundException $e) {
                }
            }
        }

        // Process again to de-duplicate locales and their fallback locales
        // Only keep the differences
        foreach ($displayLocales as $displayLocale => $_) {
            $fallback = $displayLocale;

            while (isset($fallbackMapping[$fallback])) {
                $fallback = $fallbackMapping[$fallback];
                $localeNames[$displayLocale] = array_diff(
                    $localeNames[$displayLocale],
                    $localeNames[$fallback]
                );
            }

            // If no names remain to be saved for the current locale, skip it
            if (0 === \count($localeNames[$displayLocale])) {
                continue;
            }

            foreach ($writers as $targetDir => $writer) {
                $writer->write($targetDir.'/'.$this->dirName, $displayLocale, array(
                    'Names' => $localeNames[$displayLocale],
                ));
            }
        }

        // Generate aliases, needed to enable proper fallback from alias to its
        // target
        foreach ($aliases as $alias => $aliasOf) {
            foreach ($writers as $targetDir => $writer) {
                $writer->write($targetDir.'/'.$this->dirName, $alias, array(
                    '%%ALIAS' => $aliasOf,
                ));
            }
        }

        // Create root file which maps locale codes to locale codes, for fallback
        foreach ($writers as $targetDir => $writer) {
            $writer->write($targetDir.'/'.$this->dirName, 'meta', array(
                'Locales' => $locales,
                'Aliases' => $aliases,
            ));
        }
    }

    private function generateLocaleName($locale, $displayLocale)
    {
        $name = null;

        $lang = \Locale::getPrimaryLanguage($locale);
        $script = \Locale::getScript($locale);
        $region = \Locale::getRegion($locale);
        $variants = \Locale::getAllVariants($locale);

        // Currently the only available variant is POSIX, which we don't want
        // to include in the list
        if (\count($variants) > 0) {
            return;
        }

        // Some languages are translated together with their region,
        // i.e. "en_GB" is translated as "British English"
        // we don't include these languages though because they mess up
        // the name sorting
        // $name = $this->langBundle->getLanguageName($displayLocale, $lang, $region);

        // Some languages are simply not translated
        // Example: "az" (Azerbaijani) has no translation in "af" (Afrikaans)
        if (null === ($name = $this->languageDataProvider->getName($lang, $displayLocale))) {
            return;
        }

        // "as" (Assamese) has no "Variants" block
        //if (!$langBundle->get('Variants')) {
        //    continue;
        //}

        $extras = array();

        // Discover the name of the script part of the locale
        // i.e. in zh_Hans_MO, "Hans" is the script
        if ($script) {
            // Some scripts are not translated into every language
            if (null === ($scriptName = $this->scriptDataProvider->getName($script, $displayLocale))) {
                return;
            }

            $extras[] = $scriptName;
        }

        // Discover the name of the region part of the locale
        // i.e. in de_AT, "AT" is the region
        if ($region) {
            // Some regions are not translated into every language
            if (null === ($regionName = $this->regionDataProvider->getName($region, $displayLocale))) {
                return;
            }

            $extras[] = $regionName;
        }

        if (\count($extras) > 0) {
            // Remove any existing extras
            // For example, in German, zh_Hans is "Chinesisch (vereinfacht)".
            // The latter is the script part which is already included in the
            // extras and will be appended again with the other extras.
            if (preg_match('/^(.+)\s+\([^\)]+\)$/', $name, $matches)) {
                $name = $matches[1];
            }

            $name .= ' ('.implode(', ', $extras).')';
        }

        return $name;
    }

    private function generateFallbackMapping(array $displayLocales, array $aliases)
    {
        $mapping = array();

        foreach ($displayLocales as $displayLocale => $_) {
            $mapping[$displayLocale] = null;
            $fallback = $displayLocale;

            // Recursively search for a fallback locale until one is found
            while (null !== ($fallback = Locale::getFallback($fallback))) {
                // Currently, no locale has an alias as fallback locale.
                // If this starts to be the case, we need to add code here.
                \assert(!isset($aliases[$fallback]));

                // Check whether the fallback exists
                if (isset($displayLocales[$fallback])) {
                    $mapping[$displayLocale] = $fallback;
                    break;
                }
            }
        }

        return $mapping;
    }
}
