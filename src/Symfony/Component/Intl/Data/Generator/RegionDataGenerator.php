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
    private static $preferredAlpha2ToAlpha3Mapping = [
        'CD' => 'COD',
        'DE' => 'DEU',
        'FR' => 'FRA',
        'MM' => 'MMR',
        'TL' => 'TLS',
        'YE' => 'YEM',
    ];

    private static $blacklist = [
        // Exceptional reservations
        'AC' => true, // Ascension Island
        'CP' => true, // Clipperton Island
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
    private $regionCodes = [];

    public static function isValidCountryCode($region)
    {
        if (isset(self::$blacklist[$region])) {
            return false;
        }

        // WORLD/CONTINENT/SUBCONTINENT/GROUPING
        if (ctype_digit($region) || \is_int($region)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        return $scanner->scanLocales($sourceDir.'/region');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir)
    {
        $compiler->compile($sourceDir.'/region', $tempDir);
        $compiler->compile($sourceDir.'/misc/metadata.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->regionCodes = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Countries']) && null !== $localeBundle['Countries']) {
            $data = [
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateRegionNames($localeBundle),
            ];

            $this->regionCodes = array_merge($this->regionCodes, array_keys($data['Names']));

            return $data;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $rootBundle = $reader->read($tempDir, 'root');
        $metadataBundle = $reader->read($tempDir, 'metadata');

        $this->regionCodes = array_unique($this->regionCodes);

        sort($this->regionCodes);

        $alpha2ToAlpha3 = $this->generateAlpha2ToAlpha3Mapping(array_flip($this->regionCodes), $metadataBundle);
        $alpha3ToAlpha2 = array_flip($alpha2ToAlpha3);
        asort($alpha3ToAlpha2);

        return [
            'Version' => $rootBundle['Version'],
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
                if (isset(self::$preferredAlpha2ToAlpha3Mapping[$country])) {
                    // Validate to prevent typos
                    if (!isset($aliases[self::$preferredAlpha2ToAlpha3Mapping[$country]])) {
                        throw new RuntimeException('The statically set three-letter mapping '.self::$preferredAlpha2ToAlpha3Mapping[$country].' for the country code '.$country.' seems to be invalid. Typo?');
                    }

                    $alpha3 = self::$preferredAlpha2ToAlpha3Mapping[$country];
                    $alpha2 = $aliases[$alpha3]['replacement'];

                    if ($country !== $alpha2) {
                        throw new RuntimeException('The statically set three-letter mapping '.$alpha3.' for the country code '.$country.' seems to be an alias for '.$alpha2.'. Wrong mapping?');
                    }

                    $alpha2ToAlpha3[$country] = $alpha3;
                } elseif (isset($alpha2ToAlpha3[$country])) {
                    throw new RuntimeException('Multiple three-letter mappings exist for the country code '.$country.'. Please add one of them to the property $preferredAlpha2ToAlpha3Mapping.');
                } elseif (isset($countries[$country]) && self::isValidCountryCode($alias)) {
                    $alpha2ToAlpha3[$country] = $alias;
                }
            }
        }

        asort($alpha2ToAlpha3);

        return $alpha2ToAlpha3;
    }
}
