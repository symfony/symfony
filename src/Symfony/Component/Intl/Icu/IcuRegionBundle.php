<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Icu;

use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface;
use Symfony\Component\Intl\ResourceBundle\RegionBundle;

/**
 * An ICU-specific implementation of {@link \Symfony\Component\Intl\ResourceBundle\RegionBundleInterface}.
 *
 * This class normalizes the data of the ICU .res files to satisfy the contract
 * defined in {@link \Symfony\Component\Intl\ResourceBundle\RegionBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuRegionBundle extends RegionBundle
{
    private $stubbed;

    public function __construct(StructuredBundleReaderInterface $reader)
    {
        $this->stubbed = IcuData::isStubbed();

        parent::__construct(realpath(IcuData::getResourceDirectory().'/region'), $reader);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        return $this->stubbed ? array('en') : $this->readEntry('misc', array('Locales'));
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryName($country, $locale = null)
    {
        if ('ZZ' === $country || ctype_digit((string) $country)) {
            return null;
        }

        return parent::getCountryName($country, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryNames($locale = null)
    {
        $countries = parent::getCountryNames($locale);

        if ($this->stubbed) {
            return $countries;
        }

        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        // "ZZ" is the code for unknown country
        unset($countries['ZZ']);

        // Global countries (f.i. "America") have numeric codes
        // Countries have alphabetic codes
        foreach ($countries as $code => $name) {
            // is_int() does not work, since some numbers start with '0' and
            // thus are stored as strings.
            // The (string) cast is necessary since ctype_digit() returns false
            // for integers.
            if (ctype_digit((string) $code)) {
                unset($countries[$code]);
            }
        }

        $collator = new \Collator($locale);
        $collator->asort($countries);

        return $countries;
    }
}
