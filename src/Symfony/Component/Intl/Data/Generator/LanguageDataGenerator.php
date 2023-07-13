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

use Symfony\Component\Intl\Data\Bundle\Compiler\BundleCompilerInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Util\LocaleScanner;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * The rule for compiling the language bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LanguageDataGenerator extends AbstractDataGenerator
{
    /**
     * Source: https://iso639-3.sil.org/code_tables/639/data.
     */
    private const PREFERRED_ALPHA2_TO_ALPHA3_MAPPING = [
        'ak' => 'aka',
        'ar' => 'ara',
        'ay' => 'aym',
        'az' => 'aze',
        'bo' => 'bod',
        'cr' => 'cre',
        'cs' => 'ces',
        'cy' => 'cym',
        'de' => 'deu',
        'dz' => 'dzo',
        'el' => 'ell',
        'et' => 'est',
        'eu' => 'eus',
        'fa' => 'fas',
        'ff' => 'ful',
        'fr' => 'fra',
        'gn' => 'grn',
        'hy' => 'hye',
        'hr' => 'hrv',
        'ik' => 'ipk',
        'is' => 'isl',
        'iu' => 'iku',
        'ka' => 'kat',
        'kr' => 'kau',
        'kg' => 'kon',
        'kv' => 'kom',
        'ku' => 'kur',
        'lv' => 'lav',
        'mg' => 'mlg',
        'mi' => 'mri',
        'mk' => 'mkd',
        'mn' => 'mon',
        'ms' => 'msa',
        'my' => 'mya',
        'nb' => 'nob',
        'ne' => 'nep',
        'nl' => 'nld',
        'oj' => 'oji',
        'om' => 'orm',
        'or' => 'ori',
        'ps' => 'pus',
        'qu' => 'que',
        'ro' => 'ron',
        'sc' => 'srd',
        'sk' => 'slk',
        'sq' => 'sqi',
        'sr' => 'srp',
        'sw' => 'swa',
        'uz' => 'uzb',
        'yi' => 'yid',
        'za' => 'zha',
        'zh' => 'zho',
    ];
    private const DENYLIST = [
        'root' => true, // Absolute root language
        'mul' => true, // Multiple languages
        'mis' => true, // Uncoded language
        'und' => true, // Unknown language
        'zxx' => true, // No linguistic content
    ];

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private array $languageCodes = [];

    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        return $scanner->scanLocales($sourceDir.'/lang');
    }

    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir)
    {
        $compiler->compile($sourceDir.'/lang', $tempDir);
        $compiler->compile($sourceDir.'/misc/metadata.txt', $tempDir);
    }

    protected function preGenerate()
    {
        $this->languageCodes = [];
    }

    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Languages']) && null !== $localeBundle['Languages']) {
            $names = [];
            $localizedNames = [];
            foreach (self::generateLanguageNames($localeBundle) as $language => $name) {
                if (!str_contains($language, '_')) {
                    $this->languageCodes[] = $language;
                    $names[$language] = $name;
                } else {
                    $localizedNames[$language] = $name;
                }
            }
            $data = [
                'Names' => $names,
                'LocalizedNames' => $localizedNames,
            ];

            return $data;
        }

        return null;
    }

    protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        return null;
    }

    protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $metadataBundle = $reader->read($tempDir, 'metadata');

        $this->languageCodes = array_unique($this->languageCodes);

        sort($this->languageCodes);

        return [
            'Languages' => $this->languageCodes,
            'Alpha3Languages' => $this->generateAlpha3Codes($this->languageCodes, $metadataBundle),
            'Alpha2ToAlpha3' => $this->generateAlpha2ToAlpha3Mapping($metadataBundle),
            'Alpha3ToAlpha2' => $this->generateAlpha3ToAlpha2Mapping($metadataBundle),
        ];
    }

    private static function generateLanguageNames(ArrayAccessibleResourceBundle $localeBundle): array
    {
        return array_diff_key(iterator_to_array($localeBundle['Languages']), self::DENYLIST);
    }

    private function generateAlpha3Codes(array $languageCodes, ArrayAccessibleResourceBundle $metadataBundle): array
    {
        $alpha3Codes = array_flip(array_filter($languageCodes, static function (string $language): bool {
            return 3 === \strlen($language);
        }));

        foreach ($metadataBundle['alias']['language'] as $alias => $data) {
            if (3 === \strlen($alias) && 'overlong' === $data['reason']) {
                $alpha3Codes[$alias] = true;
            }
        }

        ksort($alpha3Codes);

        return array_keys($alpha3Codes);
    }

    private function generateAlpha2ToAlpha3Mapping(ArrayAccessibleResourceBundle $metadataBundle): array
    {
        $aliases = iterator_to_array($metadataBundle['alias']['language']);
        $alpha2ToAlpha3 = [];

        foreach ($aliases as $alias => $data) {
            $language = $data['replacement'];
            if (2 === \strlen($language) && 3 === \strlen($alias) && 'overlong' === $data['reason']) {
                if (isset(self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$language])) {
                    // Validate to prevent typos
                    if (!isset($aliases[self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$language]])) {
                        throw new RuntimeException('The statically set three-letter mapping '.self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$language].' for the language code '.$language.' seems to be invalid. Typo?');
                    }

                    $alpha3 = self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$language];
                    $alpha2 = $aliases[$alpha3]['replacement'];

                    if ($language !== $alpha2) {
                        throw new RuntimeException('The statically set three-letter mapping '.$alpha3.' for the language code '.$language.' seems to be an alias for '.$alpha2.'. Wrong mapping?');
                    }

                    $alpha2ToAlpha3[$language] = $alpha3;
                } elseif (isset($alpha2ToAlpha3[$language])) {
                    throw new RuntimeException('Multiple three-letter mappings exist for the language code '.$language.'. Please add one of them to the const PREFERRED_ALPHA2_TO_ALPHA3_MAPPING.');
                } else {
                    $alpha2ToAlpha3[$language] = $alias;
                }
            }
        }

        asort($alpha2ToAlpha3);

        return $alpha2ToAlpha3;
    }

    private function generateAlpha3ToAlpha2Mapping(ArrayAccessibleResourceBundle $metadataBundle): array
    {
        $alpha3ToAlpha2 = [];

        foreach ($metadataBundle['alias']['language'] as $alias => $data) {
            $language = $data['replacement'];
            if (2 === \strlen($language) && 3 === \strlen($alias) && \in_array($data['reason'], ['overlong', 'bibliographic'], true)) {
                $alpha3ToAlpha2[$alias] = $language;
            }
        }

        asort($alpha3ToAlpha2);

        return $alpha3ToAlpha2;
    }
}
