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

use Symfony\Component\Icu\LocaleDataProvider;

/**
 * Default implementation of {@link LocaleBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5, to be removed in Symfony 3.0.
 *             Use {@link LocaleDataProvider} instead.
 */
class LocaleBundle extends LocaleDataProvider implements LocaleBundleInterface
{
    /**
     * Alias of {@link getDisplayNames()}.
     */
    public function getLocaleNames($locale = null)
    {
        return $this->getDisplayNames($locale);
    }

    /**
     * Alias of {@link getDisplayName()}.
     */
    public function getLocaleName($ofLocale, $locale = null)
    {
        return $this->getDisplayName($ofLocale, $locale);
    }

    /**
     * Alias of {@link getAliases()}.
     */
    public function getLocaleAliases()
    {
        return $this->getAliases();
    }
}
