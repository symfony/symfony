<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\ResourceBundle;

use Symphony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symphony\Component\Intl\Data\Provider\CurrencyDataProvider;
use Symphony\Component\Intl\Data\Provider\LocaleDataProvider;
use Symphony\Component\Intl\Exception\MissingResourceException;

/**
 * Default implementation of {@link CurrencyBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class CurrencyBundle extends CurrencyDataProvider implements CurrencyBundleInterface
{
    private $localeProvider;

    public function __construct(string $path, BundleEntryReaderInterface $reader, LocaleDataProvider $localeProvider)
    {
        parent::__construct($path, $reader);

        $this->localeProvider = $localeProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencySymbol($currency, $displayLocale = null)
    {
        try {
            return $this->getSymbol($currency, $displayLocale);
        } catch (MissingResourceException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyName($currency, $displayLocale = null)
    {
        try {
            return $this->getName($currency, $displayLocale);
        } catch (MissingResourceException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyNames($displayLocale = null)
    {
        try {
            return $this->getNames($displayLocale);
        } catch (MissingResourceException $e) {
            return array();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFractionDigits($currency)
    {
        try {
            return parent::getFractionDigits($currency);
        } catch (MissingResourceException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRoundingIncrement($currency)
    {
        try {
            return parent::getRoundingIncrement($currency);
        } catch (MissingResourceException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        try {
            return $this->localeProvider->getLocales();
        } catch (MissingResourceException $e) {
            return array();
        }
    }
}
