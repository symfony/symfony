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

use Symfony\Component\Intl\ResourceBundle\CurrencyBundle;
use Symfony\Component\Intl\ResourceBundle\CurrencyBundleInterface;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContext;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContext;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * The rule for compiling the currency bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CurrencyBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * @var CurrencyBundleInterface
     */
    private $currencyBundle;

    public function __construct(CurrencyBundleInterface $currencyBundle)
    {
        $this->currencyBundle = $currencyBundle;
    }

    /**
     * {@inheritdoc}
     */
    public function getBundleName()
    {
        return 'curr';
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(CompilationContext $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-currencies';

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir(array($tempDir, $tempDir.'/res'));

        // The currency data is contained in the locales and meta bundles
        // in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $context->getFilesystem()->mirror($context->getSourceDir().'/locales', $tempDir.'/txt');
            $context->getFilesystem()->copy($context->getSourceDir().'/misc/supplementalData.txt', $tempDir.'/txt/supplementalData.txt');
        } else {
            $context->getFilesystem()->mirror($context->getSourceDir().'/curr', $tempDir.'/txt');
        }

        $context->getCompiler()->compile($tempDir.'/txt', $tempDir.'/res');
        $context->getCompiler()->compile($context->getSourceDir().'/misc/currencyNumericCodes.txt', $tempDir.'/res');

        $reader = new BinaryBundleReader();
        $writer = new TextBundleWriter();

        // Collect supported locales of the bundle
        $availableLocales = $context->getLocaleScanner()->scanLocales($tempDir.'/txt');

        // Drop and regenerate txt files
        $context->getFilesystem()->remove($tempDir.'/txt');
        $context->getFilesystem()->mkdir($tempDir.'/txt');

        $currencies = array();

        // Generate a text file for each locale
        foreach ($availableLocales as $locale) {
            $bundle = $reader->read($tempDir.'/res', $locale);

            if (isset($bundle['Currencies']) && null !== $bundle['Currencies']) {
                $symbolNamePairs = iterator_to_array($bundle['Currencies']);

                // Remove the unknown currency
                unset($symbolNamePairs['XXX']);

                // No other keys but "Currencies" are needed for now
                $writer->write($tempDir.'/txt', $locale, array(
                    'Version' => $bundle['Version'],
                    'Currencies' => $symbolNamePairs,
                ));

                // Add currencies to the list of known currencies
                $currencies = array_merge($currencies, array_keys($symbolNamePairs));
            }
        }

        // Remove duplicate currencies and sort
        $currencies = array_unique($currencies);
        sort($currencies);

        // Open resource bundles that contain currency metadata
        $root = $reader->read($tempDir.'/res', 'root');
        $supplementalData = $reader->read($tempDir.'/res', 'supplementalData');
        $numericCodes = $reader->read($tempDir.'/res', 'currencyNumericCodes');

        // Generate default currency names and symbols
        $defaultSymbolNamePairs = array_map(
            function ($currency) use ($root) {
                if (isset($root['Currencies'][$currency]) && null !== $root['Currencies'][$currency]) {
                    return $root['Currencies'][$currency];
                }

                // by default both the symbol and the name equal the ISO code
                return array($currency, $currency);
            },
            $currencies
        );

        // Replace keys by currencies
        $defaultSymbolNamePairs = array_combine($currencies, $defaultSymbolNamePairs);

        // Generate and sort the mapping from 3-letter codes to numeric codes
        $alpha3ToNumericMapping = iterator_to_array($numericCodes['codeMap']);

        asort($alpha3ToNumericMapping);

        // Filter unknown currencies (e.g. "AYM")
        $alpha3ToNumericMapping = array_intersect_key($alpha3ToNumericMapping, $defaultSymbolNamePairs);

        // Write the root resource bundle
        $writer->write($tempDir.'/txt', 'root', array(
            'Version' => $root['Version'],
            'Currencies' => $defaultSymbolNamePairs,
            'CurrencyMeta' => $supplementalData['CurrencyMeta'],
            'Alpha3ToNumeric' => $alpha3ToNumericMapping,
        ));

        // The temporary directory now contains all sources to be compiled
        return $tempDir.'/txt';
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContext $context)
    {
        // Remove the temporary directory
        //$context->getFilesystem()->remove(sys_get_temp_dir().'/icu-data-currencies-source');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContext $context)
    {
        $currencies = array();

        foreach ($this->currencyBundle->getCurrencyNames('en') as $code => $name) {
            $currencies[$code] = array(
                CurrencyBundle::INDEX_NAME => $name,
                CurrencyBundle::INDEX_SYMBOL => $this->currencyBundle->getCurrencySymbol($code, 'en'),
                CurrencyBundle::INDEX_FRACTION_DIGITS => $this->currencyBundle->getFractionDigits($code),
                CurrencyBundle::INDEX_ROUNDING_INCREMENT => $this->currencyBundle->getRoundingIncrement($code),
            );
        }

        return array(
            'Currencies' => $currencies,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContext $context)
    {
    }
}
