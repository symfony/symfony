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
 * Default implementation of {@link RegionBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegionBundle extends AbstractBundle implements RegionBundleInterface
{
    /**
     * {@inheritdoc}
     */
    public function getCountryName($country, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->readEntry($locale, array('Countries', $country), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($countries = $this->readEntry($locale, array('Countries'), true))) {
            return array();
        }

        if ($countries instanceof \Traversable) {
            $countries = iterator_to_array($countries);
        }

        return $countries;
    }
}
