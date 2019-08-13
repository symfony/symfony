<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle;

use Symfony\Component\Intl\Data\Provider\LocaleDataProvider;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Default implementation of {@link LocaleBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal to be removed in 5.0.
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
            return [];
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
            return null;
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
            return [];
        }
    }
}
