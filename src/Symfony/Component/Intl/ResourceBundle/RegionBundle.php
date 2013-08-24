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
 *
 * @since v2.3.0
 */
class RegionBundle extends AbstractBundle implements RegionBundleInterface
{
    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getCountryName($country, $locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        return $this->readEntry($locale, array('Countries', $country));
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getCountryNames($locale = null)
    {
        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        if (null === ($countries = $this->readEntry($locale, array('Countries')))) {
            return array();
        }

        if ($countries instanceof \Traversable) {
            $countries = iterator_to_array($countries);
        }

        return $countries;
    }
}
