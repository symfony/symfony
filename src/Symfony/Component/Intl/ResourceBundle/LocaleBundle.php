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
 */
class LocaleBundle extends AbstractBundle implements LocaleBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLocaleName($locale, $ofLocale)
    {
        return $this->readEntry($locale, array('Locales', $ofLocale));
    }

    /**
     * {@inheritdoc}
     */
    public function getLocaleNames($locale)
    {
        if (null === ($locales = $this->readEntry($locale, array('Locales')))) {
            return array();
        }

        if ($locales instanceof \Traversable) {
            $locales = iterator_to_array($locales);
        }

        return $locales;
    }
}
