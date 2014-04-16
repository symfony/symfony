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
use Symfony\Component\Intl\ResourceBundle\Reader\BufferedBundleReader;
use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReader;

/**
 * Gives access to internationalization data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Intl
{
    /**
     * The number of resource bundles to buffer. Loading the same resource
     * bundle for n locales takes up n spots in the buffer.
     */
    const BUFFER_SIZE = 10;

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
     * @var string|bool|null
     */
    private static $icuVersion = false;

    /**
     * @var string
     */
    private static $icuDataVersion = false;

    /**
     * @var ResourceBundle\Reader\StructuredBundleReaderInterface
     */
    private static $bundleReader;

    /**
     * Returns whether the intl extension is installed.
     *
     * @return bool    Returns true if the intl extension is installed, false otherwise.
     */
    public static function isExtensionLoaded()
    {
        return class_exists('\ResourceBundle');
    }

    /**
     * Returns the bundle containing currency information.
     *
     * @return ResourceBundle\CurrencyBundleInterface The currency resource bundle.
     */
    public static function getCurrencyBundle()
    {
        if (null === self::$currencyBundle) {
            self::$currencyBundle = new IcuCurrencyBundle(self::getBundleReader());
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
            self::$languageBundle = new IcuLanguageBundle(self::getBundleReader());
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
            self::$localeBundle = new IcuLocaleBundle(self::getBundleReader());
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
            self::$regionBundle = new IcuRegionBundle(self::getBundleReader());
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
            self::$icuDataVersion = IcuData::getVersion();
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
        return '51.2';
    }

    /**
     * Returns a resource bundle reader for .php resource bundle files.
     *
     * @return ResourceBundle\Reader\StructuredBundleReaderInterface The resource reader.
     */
    private static function getBundleReader()
    {
        if (null === self::$bundleReader) {
            self::$bundleReader = new StructuredBundleReader(new BufferedBundleReader(
                IcuData::getBundleReader(),
                self::BUFFER_SIZE
            ));
        }

        return self::$bundleReader;
    }

    /**
     * This class must not be instantiated.
     */
    private function __construct() {}
}
