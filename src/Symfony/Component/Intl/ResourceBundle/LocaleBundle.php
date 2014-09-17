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

/**
 * Default implementation of {@link LocaleBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LocaleBundle extends AbstractBundle implements LocaleBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLocaleName($ofLocale, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->readEntry($locale, array('Locales', $ofLocale), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocaleNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($locales = $this->readEntry($locale, array('Locales'), true))) {
            return array();
        }

        if ($locales instanceof \Traversable) {
            $locales = iterator_to_array($locales);
        }

        return $locales;
    }
}
