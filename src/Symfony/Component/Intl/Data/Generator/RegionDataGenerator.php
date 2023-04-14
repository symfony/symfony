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
 * The rule for compiling the region bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see http://source.icu-project.org/repos/icu/icu4j/trunk/main/classes/core/src/com/ibm/icu/util/Region.java
 *
 * @internal
 */
class RegionDataGenerator extends AbstractDataGenerator
{
    /**
     * Source: https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes.
     */
    private const PREFERRED_ALPHA2_TO_ALPHA3_MAPPING = [
        'CD' => 'COD',
        'DE' => 'DEU',
        'FR' => 'FRA',
        'MM' => 'MMR',
        'TL' => 'TLS',
        'YE' => 'YEM',
    ];

    private const DENYLIST = [
        // Exceptional reservations
        'AC' => true, // Ascension Island
        'CP' => true, // Clipperton Island
        'CQ' => true, // Island of Sark
        'DG' => true, // Diego Garcia
        'EA' => true, // Ceuta & Melilla
        'EU' => true, // European Union
        'EZ' => true, // Eurozone
        'IC' => true, // Canary Islands
        'TA' => true, // Tristan da Cunha
        'UN' => true, // United Nations
        // User-assigned
        'QO' => true, // Outlying Oceania
        'XA' => true, // Pseudo-Accents
        'XB' => true, // Pseudo-Bidi
        'XK' => true, // Kosovo
        // Misc
        'ZZ' => true, // Unknown Region
    ];

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private array $regionCodes = [];

    public static function isValidCountryCode(int|string|null $region): bool
    {
        if (isset(self::DENYLIST[$region])) {
            return false;
        }

        // WORLD/CONTINENT/SUBCONTINENT/GROUPING
        if (\is_int($region) || ctype_digit($region)) {
            return false;
        }

        return true;
    }

    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        return $scanner->scanLocales($sourceDir.'/region');
    }

    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir): void
    {
        $compiler->compile($sourceDir.'/region', $tempDir);
        $compiler->compile($sourceDir.'/misc/metadata.txt', $tempDir);
    }

    protected function preGenerate(): void
    {
        $this->regionCodes = [];
    }

    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Countries']) && null !== $localeBundle['Countries']) {
            $data = [
                'Names' => $this->generateRegionNames($localeBundle),
            ];

            $this->regionCodes = array_merge($this->regionCodes, array_keys($data['Names']));

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

        $this->regionCodes = array_unique($this->regionCodes);

        sort($this->regionCodes);

        $alpha2ToAlpha3 = $this->generateAlpha2ToAlpha3Mapping(array_flip($this->regionCodes), $metadataBundle);
        $alpha3ToAlpha2 = array_flip($alpha2ToAlpha3);
        asort($alpha3ToAlpha2);

        return [
            'Regions' => $this->regionCodes,
            'Alpha2ToAlpha3' => $alpha2ToAlpha3,
            'Alpha3ToAlpha2' => $alpha3ToAlpha2,
        ];
    }

    protected function generateRegionNames(ArrayAccessibleResourceBundle $localeBundle): array
    {
        $unfilteredRegionNames = iterator_to_array($localeBundle['Countries']);
        $regionNames = [];

        foreach ($unfilteredRegionNames as $region => $regionName) {
            if (!self::isValidCountryCode($region)) {
                continue;
            }

            $regionNames[$region] = $regionName;
        }

        return $regionNames;
    }

    private function generateAlpha2ToAlpha3Mapping(array $countries, ArrayAccessibleResourceBundle $metadataBundle): array
    {
        $aliases = iterator_to_array($metadataBundle['alias']['territory']);
        $alpha2ToAlpha3 = [];

        foreach ($aliases as $alias => $data) {
            $country = $data['replacement'];
            if (2 === \strlen($country) && 3 === \strlen($alias) && 'overlong' === $data['reason']) {
                if (isset(self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$country])) {
                    // Validate to prevent typos
                    if (!isset($aliases[self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$country]])) {
                        throw new RuntimeException('The statically set three-letter mapping '.self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$country].' for the country code '.$country.' seems to be invalid. Typo?');
                    }

                    $alpha3 = self::PREFERRED_ALPHA2_TO_ALPHA3_MAPPING[$country];
                    $alpha2 = $aliases[$alpha3]['replacement'];

                    if ($country !== $alpha2) {
                        throw new RuntimeException('The statically set three-letter mapping '.$alpha3.' for the country code '.$country.' seems to be an alias for '.$alpha2.'. Wrong mapping?');
                    }

                    $alpha2ToAlpha3[$country] = $alpha3;
                } elseif (isset($alpha2ToAlpha3[$country])) {
                    throw new RuntimeException('Multiple three-letter mappings exist for the country code '.$country.'. Please add one of them to the const PREFERRED_ALPHA2_TO_ALPHA3_MAPPING.');
                } elseif (isset($countries[$country]) && self::isValidCountryCode($alias)) {
                    $alpha2ToAlpha3[$country] = $alias;
                }
            }
        }

        asort($alpha2ToAlpha3);

        return $alpha2ToAlpha3;
    }
}
