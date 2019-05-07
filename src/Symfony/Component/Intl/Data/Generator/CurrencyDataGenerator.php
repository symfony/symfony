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

/**
 * The rule for compiling the currency bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class CurrencyDataGenerator extends AbstractDataGenerator
{
    private static $blacklist = [
        'XBA' => true, // European Composite Unit
        'XBB' => true, // European Monetary Unit
        'XBC' => true, // European Unit of Account (XBC)
        'XBD' => true, // European Unit of Account (XBD)
        'XUA' => true, // ADB Unit of Account
        'XAU' => true, // Gold
        'XAG' => true, // Silver
        'XPT' => true, // Platinum
        'XPD' => true, // Palladium
        'XSU' => true, // Sucre
        'XDR' => true, // Special Drawing Rights
        'XTS' => true, // Testing Currency Code
        'XXX' => true, // Unknown Currency
    ];

    /**
     * Collects all available currency codes.
     *
     * @var string[]
     */
    private $currencyCodes = [];

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, $sourceDir)
    {
        return $scanner->scanLocales($sourceDir.'/curr');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, $sourceDir, $tempDir)
    {
        $compiler->compile($sourceDir.'/curr', $tempDir);
        $compiler->compile($sourceDir.'/misc/currencyNumericCodes.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->currencyCodes = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleEntryReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (isset($localeBundle['Currencies']) && null !== $localeBundle['Currencies']) {
            $data = [
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateSymbolNamePairs($localeBundle),
            ];

            $this->currencyCodes = array_merge($this->currencyCodes, array_keys($data['Names']));

            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleEntryReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');

        return [
            'Version' => $rootBundle['Version'],
            'Names' => $this->generateSymbolNamePairs($rootBundle),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleEntryReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');
        $supplementalDataBundle = $reader->read($tempDir, 'supplementalData');
        $numericCodesBundle = $reader->read($tempDir, 'currencyNumericCodes');

        $this->currencyCodes = array_unique($this->currencyCodes);

        sort($this->currencyCodes);

        $data = [
            'Version' => $rootBundle['Version'],
            'Currencies' => $this->currencyCodes,
            'Meta' => $this->generateCurrencyMeta($supplementalDataBundle),
            'Alpha3ToNumeric' => $this->generateAlpha3ToNumericMapping($numericCodesBundle, $this->currencyCodes),
        ];

        $data['NumericToAlpha3'] = $this->generateNumericToAlpha3Mapping($data['Alpha3ToNumeric']);

        return $data;
    }

    /**
     * @return array
     */
    private function generateSymbolNamePairs(ArrayAccessibleResourceBundle $rootBundle)
    {
        $symbolNamePairs = iterator_to_array($rootBundle['Currencies']);

        // Remove unwanted currencies
        $symbolNamePairs = array_diff_key($symbolNamePairs, self::$blacklist);

        return $symbolNamePairs;
    }

    private function generateCurrencyMeta(ArrayAccessibleResourceBundle $supplementalDataBundle)
    {
        // The metadata is already de-duplicated. It contains one key "DEFAULT"
        // which is used for currencies that don't have dedicated entries.
        return iterator_to_array($supplementalDataBundle['CurrencyMeta']);
    }

    private function generateAlpha3ToNumericMapping(ArrayAccessibleResourceBundle $numericCodesBundle, array $currencyCodes)
    {
        $alpha3ToNumericMapping = iterator_to_array($numericCodesBundle['codeMap']);

        asort($alpha3ToNumericMapping);

        // Filter unknown currencies (e.g. "AYM")
        $alpha3ToNumericMapping = array_intersect_key($alpha3ToNumericMapping, array_flip($currencyCodes));

        return $alpha3ToNumericMapping;
    }

    private function generateNumericToAlpha3Mapping(array $alpha3ToNumericMapping)
    {
        $numericToAlpha3Mapping = [];

        foreach ($alpha3ToNumericMapping as $alpha3 => $numeric) {
            // Make sure that the mapping is stored as table and not as array
            $numeric = (string) $numeric;

            if (!isset($numericToAlpha3Mapping[$numeric])) {
                $numericToAlpha3Mapping[$numeric] = [];
            }

            $numericToAlpha3Mapping[$numeric][] = $alpha3;
        }

        return $numericToAlpha3Mapping;
    }
}
