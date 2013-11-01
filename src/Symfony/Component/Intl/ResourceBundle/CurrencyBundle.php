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

use Symfony\Component\Icu\CurrencyDataProvider;
use Symfony\Component\Intl\Locale;

/**
 * Default implementation of {@link CurrencyBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link CurrencyDataProvider} instead.
 */
class CurrencyBundle extends CurrencyDataProvider implements CurrencyBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        return Locale::getLocales();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencySymbol($currency, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->getSymbol($currency, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyName($currency, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->getName($currency, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrencyNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->getNames($locale);
    }
}
