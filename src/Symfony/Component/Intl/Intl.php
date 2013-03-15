<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl;

use Symfony\Component\Icu\IcuCurrencyBundle;
use Symfony\Component\Icu\IcuData;
use Symfony\Component\Icu\IcuLanguageBundle;
use Symfony\Component\Icu\IcuLocaleBundle;
use Symfony\Component\Icu\IcuRegionBundle;
use Symfony\Component\Intl\Exception\InvalidArgumentException;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
use Symfony\Component\Intl\ResourceBundle\Reader\BufferedBundleReader;
use Symfony\Component\Intl\ResourceBundle\Reader\PhpBundleReader;
use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReader;
use Symfony\Component\Intl\ResourceBundle\Stub\StubCurrencyBundle;
use Symfony\Component\Intl\ResourceBundle\Stub\StubLanguageBundle;
use Symfony\Component\Intl\ResourceBundle\Stub\StubLocaleBundle;
use Symfony\Component\Intl\ResourceBundle\Stub\StubRegionBundle;

/**
 * Gives access to internationalization data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Intl
{
    /**
     * Load data from the Icu component.
     */
    const ICU = 0;

    /**
     * Load data from the stub files of the Intl component.
     */
    const STUB = 1;

    /**
     * The number of resource bundles to buffer. Loading the same resource
     * bundle for n locales takes up n spots in the buffer.
     */
    const BUFFER_SIZE = 10;

    /**
     * The accepted values for the {@link $dataSource} property.
     *
     * @var array
     */
    private static $allowedDataSources = array(
        self::ICU => 'Intl::ICU',
        self::STUB => 'Intl::STUB',
    );

    /**
     * @var integer
     */
    private static $dataSource;

    /**
     * @var ResourceBundle\CurrencyBundleInterface
     */
    private static $currencyBundle;

    /**
     * @var ResourceBundle\LanguageBundleInterface
     */
    private static $languageBundle;

    /**
     * @var ResourceBundle\LocaleBundleInterface
     */
    private static $localeBundle;

    /**
     * @var ResourceBundle\RegionBundleInterface
     */
    private static $regionBundle;

    /**
     * @var string|Boolean|null
     */
    private static $icuVersion = false;

    /**
     * @var string
     */
    private static $icuDataVersion = false;

    /**
     * @var ResourceBundle\Reader\StructuredBundleReaderInterface
     */
    private static $phpReader;

    /**
     * @var ResourceBundle\Reader\StructuredBundleReaderInterface
     */
    private static $binaryReader;

    /**
     * Returns whether the intl extension is installed.
     *
     * @return Boolean Returns true if the intl extension is installed, false otherwise.
     */
    public static function isExtensionLoaded()
    {
        return IcuData::isLoadable();
    }

    /**
     * Sets the data source from which to load the resource bundles.
     *
     * @param integer $dataSource One of the constants {@link Intl::ICU} or
     *                            {@link Intl::STUB}.
     *
     * @throws InvalidArgumentException If the data source is invalid.
     *
     * @see getData>Source
     */
    public static function setDataSource($dataSource)
    {
        if (!isset(self::$allowedDataSources[$dataSource])) {
            throw new InvalidArgumentException(sprintf(
                'The data sources should be one of %s',
                implode(', ', self::$allowedDataSources)
            ));
        }

        if (self::ICU === $dataSource && !IcuData::isLoadable()) {
            throw new InvalidArgumentException(
                'The data source cannot be set to Intl::ICU if the intl ' .
                'extension is not installed.'
            );
        }

        if ($dataSource !== self::$dataSource) {
            self::$currencyBundle = null;
            self::$languageBundle = null;
            self::$localeBundle = null;
            self::$regionBundle = null;
        }

        self::$dataSource = $dataSource;
    }

    /**
     * Returns the data source from which to load the resource bundles.
     *
     * If {@link setDataSource()} has not been called, the data source will be
     * chosen depending on whether the intl extension is installed or not:
     *
     *   * If the extension is present, the bundles will be loaded from the Icu
     *     component;
     *   * Otherwise, the bundles will be loaded from the stub files in the
     *     Intl component.
     *
     * @return integer One of the constants {@link Intl::ICU} or
     *                 {@link Intl::STUB}.
     */
    public static function getDataSource()
    {
        if (null === self::$dataSource) {
            self::$dataSource = IcuData::isLoadable() ? self::ICU : self::STUB;
        }

        return self::$dataSource;
    }

    /**
     * Returns the bundle containing currency information.
     *
     * @return ResourceBundle\CurrencyBundleInterface The currency resource bundle.
     */
    public static function getCurrencyBundle()
    {
        if (null === self::$currencyBundle) {
            self::$currencyBundle = self::ICU === self::getDataSource()
                ? new IcuCurrencyBundle(self::getBinaryReader())
                : new StubCurrencyBundle(self::getPhpReader());
        }

        return self::$currencyBundle;
    }

    /**
     * Returns the bundle containing language information.
     *
     * @return ResourceBundle\LanguageBundleInterface The language resource bundle.
     */
    public static function getLanguageBundle()
    {
        if (null === self::$languageBundle) {
            self::$languageBundle = self::ICU === self::getDataSource()
                ? new IcuLanguageBundle(self::getBinaryReader())
                : new StubLanguageBundle(self::getPhpReader());
        }

        return self::$languageBundle;
    }

    /**
     * Returns the bundle containing locale information.
     *
     * @return ResourceBundle\LocaleBundleInterface The locale resource bundle.
     */
    public static function getLocaleBundle()
    {
        if (null === self::$localeBundle) {
            self::$localeBundle = self::ICU === self::getDataSource()
                ? new IcuLocaleBundle(self::getBinaryReader())
                : new StubLocaleBundle(self::getPhpReader());
        }

        return self::$localeBundle;
    }

    /**
     * Returns the bundle containing region information.
     *
     * @return ResourceBundle\RegionBundleInterface The region resource bundle.
     */
    public static function getRegionBundle()
    {
        if (null === self::$regionBundle) {
            self::$regionBundle = self::ICU === self::getDataSource()
                ? new IcuRegionBundle(self::getBinaryReader())
                : new StubRegionBundle(self::getPhpReader());
        }

        return self::$regionBundle;
    }

    /**
     * Returns the version of the installed ICU library.
     *
     * @return null|string The ICU version or NULL if it could not be determined.
     */
    public static function getIcuVersion()
    {
        if (false === self::$icuVersion) {
            if (!self::isExtensionLoaded()) {
                self::$icuVersion = self::getIcuStubVersion();
            } elseif (defined('INTL_ICU_VERSION')) {
                self::$icuVersion = INTL_ICU_VERSION;
            } else {
                try {
                    $reflector = new \ReflectionExtension('intl');
                    ob_start();
                    $reflector->info();
                    $output = strip_tags(ob_get_clean());
                    preg_match('/^ICU version (?:=>)?(.*)$/m', $output, $matches);

                    self::$icuVersion = trim($matches[1]);
                } catch (\ReflectionException $e) {
                    self::$icuVersion = null;
                }
            }
        }

        return self::$icuVersion;
    }

    /**
     * Returns the version of the installed ICU data.
     *
     * @return string The version of the installed ICU data.
     */
    public static function getIcuDataVersion()
    {
        if (false === self::$icuDataVersion) {
            self::$icuDataVersion = self::ICU === self::getDataSource()
                ? IcuData::getVersion()
                : file_get_contents(__DIR__ . '/Resources/version.txt');
        }

        return self::$icuDataVersion;
    }

    /**
     * Returns the ICU version that the stub classes mimic.
     *
     * @return string The ICU version of the stub classes.
     */
    public static function getIcuStubVersion()
    {
        return '50.1.0';
    }

    /**
     * Returns a resource bundle reader for .php resource bundle files.
     *
     * @return ResourceBundle\Reader\StructuredBundleReaderInterface The resource reader.
     */
    private static function getPhpReader()
    {
        if (null === self::$phpReader) {
            self::$phpReader = new StructuredBundleReader(new BufferedBundleReader(
                new PhpBundleReader(),
                self::BUFFER_SIZE
            ));
        }

        return self::$phpReader;
    }

    /**
     * Returns a resource bundle reader for binary .res resource bundle files.
     *
     * @return ResourceBundle\Reader\StructuredBundleReaderInterface The resource reader.
     */
    private static function getBinaryReader()
    {
        if (null === self::$binaryReader) {
            self::$binaryReader = new StructuredBundleReader(new BufferedBundleReader(
                new BinaryBundleReader(),
                self::BUFFER_SIZE
            ));
        }

        return self::$binaryReader;
    }

    /**
     * This class must not be instantiated.
     */
    private function __construct() {}
}
