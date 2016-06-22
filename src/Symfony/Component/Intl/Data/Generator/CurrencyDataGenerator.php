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

use Symfony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
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
    const UNKNOWN_CURRENCY_ID = 'XXX';

    const EUROPEAN_COMPOSITE_UNIT_ID = 'XBA';

    const EUROPEAN_MONETARY_UNIT_ID = 'XBB';

    const EUROPEAN_UNIT_OF_ACCOUNT_XBC_ID = 'XBC';

    const EUROPEAN_UNIT_OF_ACCOUNT_XBD_ID = 'XBD';

    const TESTING_CURRENCY_CODE_ID = 'XTS';

    const ADB_UNIT_OF_ACCOUNT_ID = 'XUA';

    const GOLD_ID = 'XAU';

    const SILVER_ID = 'XAG';

    const PLATINUM_ID = 'XPT';

    const PALLADIUM_ID = 'XPD';

    const SUCRE_ID = 'XSU';

    const SPECIAL_DRAWING_RIGHTS_ID = 'XDR';

    /**
     * Monetary units excluded from generation.
     *
     * @var array
     */
    private static $blacklist = array(
        self::UNKNOWN_CURRENCY_ID => true,
        self::EUROPEAN_COMPOSITE_UNIT_ID => true,
        self::EUROPEAN_MONETARY_UNIT_ID => true,
        self::EUROPEAN_UNIT_OF_ACCOUNT_XBC_ID => true,
        self::EUROPEAN_UNIT_OF_ACCOUNT_XBD_ID => true,
        self::TESTING_CURRENCY_CODE_ID => true,
        self::ADB_UNIT_OF_ACCOUNT_ID => true,
        self::GOLD_ID => true,
        self::SILVER_ID => true,
        self::PLATINUM_ID => true,
        self::PALLADIUM_ID => true,
        self::SUCRE_ID => true,
        self::SPECIAL_DRAWING_RIGHTS_ID => true,
    );

    /**
     * Collects all available currency codes.
     *
     * @var string[]
     */
    private $currencyCodes = array();

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
    protected function compileTemporaryBundles(GenrbCompiler $compiler, $sourceDir, $tempDir)
    {
        $compiler->compile($sourceDir.'/curr', $tempDir);
        $compiler->compile($sourceDir.'/misc/currencyNumericCodes.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->currencyCodes = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        if (isset($localeBundle['Currencies']) && null !== $localeBundle['Currencies']) {
            $data = array(
                'Version' => $localeBundle['Version'],
                'Names' => $this->generateSymbolNamePairs($localeBundle),
            );

            $this->currencyCodes = array_merge($this->currencyCodes, array_keys($data['Names']));

            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');

        return array(
            'Version' => $rootBundle['Version'],
            'Names' => $this->generateSymbolNamePairs($rootBundle),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');
        $supplementalDataBundle = $reader->read($tempDir, 'supplementalData');
        $numericCodesBundle = $reader->read($tempDir, 'currencyNumericCodes');

        $this->currencyCodes = array_unique($this->currencyCodes);

        sort($this->currencyCodes);

        $data = array(
            'Version' => $rootBundle['Version'],
            'Currencies' => $this->currencyCodes,
            'Meta' => $this->generateCurrencyMeta($supplementalDataBundle),
            'Alpha3ToNumeric' => $this->generateAlpha3ToNumericMapping($numericCodesBundle, $this->currencyCodes),
        );

        $data['NumericToAlpha3'] = $this->generateNumericToAlpha3Mapping($data['Alpha3ToNumeric']);

        return $data;
    }

    /**
     * @param ArrayAccessibleResourceBundle $rootBundle
     *
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
        $numericToAlpha3Mapping = array();

        foreach ($alpha3ToNumericMapping as $alpha3 => $numeric) {
            // Make sure that the mapping is stored as table and not as array
            $numeric = (string) $numeric;

            if (!isset($numericToAlpha3Mapping[$numeric])) {
                $numericToAlpha3Mapping[$numeric] = array();
            }

            $numericToAlpha3Mapping[$numeric][] = $alpha3;
        }

        return $numericToAlpha3Mapping;
    }
}
