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
        $tempDir = sys_get_temp_dir() . '/icu-data-locales';

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir($tempDir);

        $this->generateTextFiles($tempDir, $this->scanLocales($context));

        return $tempDir;
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContextInterface $context)
    {
        $context->getFilesystem()->remove(sys_get_temp_dir() . '/icu-data-locales');
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

    private function scanLocales(CompilationContextInterface $context)
    {
        $tempDir = sys_get_temp_dir() . '/icu-data-locales-source';

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir($tempDir);

        // Temporarily generate the resource bundles
        $context->getCompiler()->compile($context->getSourceDir() . '/locales', $tempDir);

        // Discover the list of supported locales, which are the names of the resource
        // bundles in the "locales" directory
        $locales = glob($tempDir . '/*.res');

        // Remove file extension and sort
        array_walk($locales, function (&$locale) { $locale = basename($locale, '.res'); });
        sort($locales);

        // Delete unneeded locales
        foreach ($locales as $key => $locale) {
            // Delete all aliases from the list
            // i.e., "az_AZ" is an alias for "az_Latn_AZ"
            $content = file_get_contents($context->getSourceDir() . '/locales/' . $locale . '.txt');

            // The key "%%ALIAS" is not accessible through the \ResourceBundle class,
            // so look in the original .txt file instead
            if (strpos($content, '%%ALIAS') !== false) {
                unset($locales[$key]);
            }

            // Delete locales that have no content (i.e. only "Version" key)
            try {
                $bundle = new \ResourceBundle($locale, $tempDir);
            } catch (\Exception $e) {
                // HHVM compatibility: constructor throws on invalid resource
                $bundle = null;
            }

            if (null === $bundle) {
                throw new RuntimeException('The resource bundle for locale ' . $locale . ' could not be loaded from directory ' . $tempDir);
            }

            // There seems to be no other way for identifying all keys in this specific
            // resource bundle
            if (array_keys(iterator_to_array($bundle)) === array('Version')) {
                unset($locales[$key]);
            }
        }

        $context->getFilesystem()->remove($tempDir);

        return $locales;
    }

    private function generateTextFiles($targetDirectory, array $locales)
    {
        $displayLocales = array_unique(array_merge(
            $this->languageBundle->getLocales(),
            $this->regionBundle->getLocales()
        ));

        $txtWriter = new TextBundleWriter();

        // Generate a list of locale names in the language of each display locale
        // Each locale name has the form: "Language (Script, Region, Variant1, ...)
        // Script, Region and Variants are optional. If none of them is available,
        // the braces are not printed.
        foreach ($displayLocales as $displayLocale) {
            // Don't include ICU's root resource bundle
            if ('root' === $displayLocale) {
                continue;
            }

            $names = array();

            foreach ($locales as $locale) {
                // Don't include ICU's root resource bundle
                if ($locale === 'root') {
                    continue;
                }

                if (null !== ($name = $this->generateLocaleName($locale, $displayLocale))) {
                    $names[$locale] = $name;
                }
            }

            // If no names could be generated for the current locale, skip it
            if (0 === count($names)) {
                continue;
            }

            $txtWriter->write($targetDirectory, $displayLocale, array('Locales' => $names));
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
            return;
        }

        // Some languages are translated together with their region,
        // i.e. "en_GB" is translated as "British English"
        // we don't include these languages though because they mess up
        // the name sorting
        // $name = $this->langBundle->getLanguageName($displayLocale, $lang, $region);

        // Some languages are simply not translated
        // Example: "az" (Azerbaijani) has no translation in "af" (Afrikaans)
        if (null === ($name = $this->languageBundle->getLanguageName($lang, null, $displayLocale))) {
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
            if (null === ($scriptName = $this->languageBundle->getScriptName($script, $lang, $displayLocale))) {
                return;
            }

            $extras[] = $scriptName;
        }

        // Discover the name of the region part of the locale
        // i.e. in de_AT, "AT" is the region
        if ($region) {
            // Some regions are not translated into every language
            if (null === ($regionName = $this->regionBundle->getCountryName($region, $displayLocale))) {
                return;
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
