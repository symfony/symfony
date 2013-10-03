<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer\Rule;

use Symfony\Component\Intl\Exception\NoSuchEntryException;
use Symfony\Component\Intl\Exception\RuntimeException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContextInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContextInterface;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;

/**
 * The rule for compiling the locale bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * @var \Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface
     */
    private $languageBundle;

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\RegionBundleInterface
     */
    private $regionBundle;

    public function __construct()
    {
        $this->languageBundle = Intl::getLanguageBundle();
        $this->regionBundle = Intl::getRegionBundle();
    }

    /**
     * {@inheritdoc}
     */
    public function getBundleName()
    {
        return 'locales';
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(CompilationContextInterface $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-locales';

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir($tempDir);

        $locales = $context->getLocaleScanner()->scanLocales($context->getSourceDir().'/locales');
        $aliases = $context->getLocaleScanner()->scanAliases($context->getSourceDir().'/locales');

        $writer = new TextBundleWriter();

        $this->generateTextFiles($writer, $tempDir, $locales, $aliases);

        // Generate aliases, needed to enable proper fallback from alias to its
        // target
        foreach ($aliases as $alias => $aliasOf) {
            $writer->write($tempDir, $alias, array('%%ALIAS' => $aliasOf));
        }

        // Create misc file with all available locales
        $writer->write($tempDir, 'misc', array(
            'Locales' => $locales,
            'Aliases' => $aliases,
        ), false);

        return $tempDir;
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContextInterface $context)
    {
        $context->getFilesystem()->remove(sys_get_temp_dir().'/icu-data-locales');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContextInterface $context)
    {
        return array(
            'Locales' => Intl::getLocaleBundle()->getLocaleNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContextInterface $context)
    {
    }

    private function generateTextFiles(TextBundleWriter $writer, $targetDirectory, array $locales, array $aliases)
    {
        // Collect locales for which translations exist
        $displayLocales = array_unique(array_merge(
            $this->languageBundle->getLocales(),
            $this->regionBundle->getLocales()
        ));

        // Flip to facilitate lookup
        $displayLocales = array_flip($displayLocales);
        $locales = array_flip($locales);

        // Don't generate names for aliases (names will be generated for the
        // locale they are duplicating)
        $displayLocales = array_diff_key($displayLocales, $aliases);

        // Generate a list of (existing) locale fallbacks
        $fallbacks = array();

        foreach ($displayLocales as $displayLocale => $_) {
            $fallbacks[$displayLocale] = null;
            $fallback = $displayLocale;

            // Recursively search for a fallback locale until one is found
            while (null !== ($fallback = Intl::getFallbackLocale($fallback))) {
                // Currently, no locale has an alias as fallback locale.
                // If this starts to be the case, we need to add code here.
                assert(!isset($aliases[$fallback]));

                // Check whether the fallback exists
                if (isset($displayLocales[$fallback])) {
                    $fallbacks[$displayLocale] = $fallback;
                    break;
                }
            }
        }

        // Since fallbacks are always shorter than their source, we can sort
        // the display locales so that fallbacks are always processed before
        // their variants
        ksort($displayLocales);

        $localeNames = array();

        // Generate locale names for all locales that have translations in
        // at least the language or the region bundle
        foreach ($displayLocales as $displayLocale => $_) {
            $localeNames[$displayLocale] = array();

            foreach ($locales as $locale => $__) {
                try {
                    // Generate a locale name in the language of each display locale
                    // Each locale name has the form: "Language (Script, Region, Variant1, ...)
                    // Script, Region and Variants are optional. If none of them is
                    // available, the braces are not printed.
                    if (null !== ($name = $this->generateLocaleName($locale, $displayLocale))) {
                        $localeNames[$displayLocale][$locale] = $name;
                    }
                } catch (NoSuchEntryException $e) {
                }
            }

            // Compare names with the names of the fallback locales and only
            // keep the differences
            $fallback = $displayLocale;

            while (isset($fallbacks[$fallback])) {
                $fallback = $fallbacks[$fallback];
                $localeNames[$displayLocale] = array_diff(
                    $localeNames[$displayLocale],
                    $localeNames[$fallback]
                );
            }

            // If no names remain to be saved for the current locale, skip it
            if (0 === count($localeNames[$displayLocale])) {
                continue;
            }

            $writer->write($targetDirectory, $displayLocale, array('Locales' => $localeNames[$displayLocale]));
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
        if (count($variants) > 0) {
            return null;
        }

        // Some languages are translated together with their region,
        // i.e. "en_GB" is translated as "British English"
        // we don't include these languages though because they mess up
        // the name sorting
        // $name = $this->langBundle->getLanguageName($displayLocale, $lang, $region);

        // Some languages are simply not translated
        // Example: "az" (Azerbaijani) has no translation in "af" (Afrikaans)
        if (null === ($name = $this->languageBundle->getLanguageName($lang, null, $displayLocale))) {
            return null;
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
            if (null === ($scriptName = $this->languageBundle->getScriptName($script, $lang, $displayLocale))) {
                return null;
            }

            $extras[] = $scriptName;
        }

        // Discover the name of the region part of the locale
        // i.e. in de_AT, "AT" is the region
        if ($region) {
            // Some regions are not translated into every language
            if (null === ($regionName = $this->regionBundle->getCountryName($region, $displayLocale))) {
                return null;
            }

            $extras[] = $regionName;
        }

        if (count($extras) > 0) {
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
}
