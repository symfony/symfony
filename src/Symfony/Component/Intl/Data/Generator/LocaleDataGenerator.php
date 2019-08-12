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
use Symfony\Component\Intl\Data\Bundle\Compiler\BundleCompilerInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Util\LocaleScanner;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * The rule for compiling the locale bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
class LocaleDataGenerator extends AbstractDataGenerator
{
    use FallbackTrait;

    private $locales = [];
    private $localeAliases = [];

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, $sourceDir)
    {
        $this->locales = $scanner->scanLocales($sourceDir.'/locales');
        $this->localeAliases = $scanner->scanAliases($sourceDir.'/locales');

        return $this->locales;
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, $sourceDir, $tempDir)
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $tempDir.'/lang',
            $tempDir.'/region',
        ]);
        $compiler->compile($sourceDir.'/lang', $tempDir.'/lang');
        $compiler->compile($sourceDir.'/region', $tempDir.'/region');
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleEntryReaderInterface $reader, $tempDir, $displayLocale)
    {
        // Don't generate aliases, as they are resolved during runtime
        // Unless an alias is needed as fallback for de-duplication purposes
        if (isset($this->localeAliases[$displayLocale]) && !$this->generatingFallback) {
            return null;
        }

        // Generate locale names for all locales that have translations in
        // at least the language or the region bundle
        $displayFormat = $reader->readEntry($tempDir.'/lang', $displayLocale, ['localeDisplayPattern']);
        $pattern = $displayFormat['pattern'] ?? '{0} ({1})';
        $separator = $displayFormat['separator'] ?? '{0}, {1}';
        $localeNames = [];
        foreach ($this->locales as $locale) {
            // Ensure a normalized list of pure locales
            if (\Locale::getAllVariants($locale)) {
                continue;
            }

            try {
                // Generate a locale name in the language of each display locale
                // Each locale name has the form: "Language (Script, Region, Variant1, ...)
                // Script, Region and Variants are optional. If none of them is
                // available, the braces are not printed.
                $localeNames[$locale] = $this->generateLocaleName($reader, $tempDir, $locale, $displayLocale, $pattern, $separator);
            } catch (MissingResourceException $e) {
                // Silently ignore incomplete locale names
                // In this case one should configure at least one fallback locale that is complete (e.g. English) during
                // runtime. Alternatively a translation for the missing resource can be proposed upstream.
            }
        }

        $data = [
            'Names' => $localeNames,
        ];

        // Don't de-duplicate a fallback locale
        // Ensures the display locale can be de-duplicated on itself
        if ($this->generatingFallback) {
            return $data;
        }

        // Process again to de-duplicate locale and its fallback locales
        // Only keep the differences
        $fallbackData = $this->generateFallbackData($reader, $tempDir, $displayLocale);
        if (isset($fallbackData['Names'])) {
            $data['Names'] = array_diff($data['Names'], $fallbackData['Names']);
        }
        if (!$data['Names']) {
            return null;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleEntryReaderInterface $reader, $tempDir)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleEntryReaderInterface $reader, $tempDir)
    {
        return [
            'Locales' => $this->locales,
            'Aliases' => $this->localeAliases,
        ];
    }

    /**
     * @return string
     */
    private function generateLocaleName(BundleEntryReaderInterface $reader, $tempDir, $locale, $displayLocale, $pattern, $separator)
    {
        // Apply generic notation using square brackets as described per http://cldr.unicode.org/translation/language-names
        $name = str_replace(['(', ')'], ['[', ']'], $reader->readEntry($tempDir.'/lang', $displayLocale, ['Languages', \Locale::getPrimaryLanguage($locale)]));
        $extras = [];

        // Discover the name of the script part of the locale
        // i.e. in zh_Hans_MO, "Hans" is the script
        if ($script = \Locale::getScript($locale)) {
            $extras[] = str_replace(['(', ')'], ['[', ']'], $reader->readEntry($tempDir.'/lang', $displayLocale, ['Scripts', $script]));
        }

        // Discover the name of the region part of the locale
        // i.e. in de_AT, "AT" is the region
        if ($region = \Locale::getRegion($locale)) {
            if (!RegionDataGenerator::isValidCountryCode($region)) {
                throw new MissingResourceException('Skipping "'.$locale.'" due an invalid country.');
            }

            $extras[] = str_replace(['(', ')'], ['[', ']'], $reader->readEntry($tempDir.'/region', $displayLocale, ['Countries', $region]));
        }

        if ($extras) {
            $extra = array_shift($extras);
            foreach ($extras as $part) {
                $extra = str_replace(['{0}', '{1}'], [$extra,  $part], $separator);
            }

            $name = str_replace(['{0}', '{1}'], [$name,  $extra], $pattern);
        }

        return $name;
    }
}
