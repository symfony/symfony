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

use Symphony\Component\Intl\Data\Provider\LocaleDataProvider;
use Symphony\Component\Intl\Exception\MissingResourceException;

/**
 * Default implementation of {@link LocaleBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LocaleBundle extends LocaleDataProvider implements LocaleBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        try {
            return parent::getLocales();
        } catch (MissingResourceException $e) {
            return array();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLocaleName($locale, $displayLocale = null)
    {
        try {
            return $this->getName($locale, $displayLocale);
        } catch (MissingResourceException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLocaleNames($displayLocale = null)
    {
        try {
            return $this->getNames($displayLocale);
        } catch (MissingResourceException $e) {
            return array();
        }
    }
}
