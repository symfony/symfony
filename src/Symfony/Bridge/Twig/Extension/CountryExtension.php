<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Intl\Intl;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Translate country code in the ISO 3166-1 alpha 2 pattern to country name.
 *
 * @author Rafael Mello <merorafael@gmail.com>
 */
class CountryExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('country', array($this, 'getCountryName')),
        );
    }

    /**
     * Return the country name using the Locale class.
     *
     * @param string      $isoCode Country ISO 3166-1 alpha 2 code
     * @param null|string $locale  Locale code
     *
     * @return null|string Country name
     */
    public function getCountryName($isoCode, $locale = null)
    {
        if (null === $isoCode) {
            return;
        }
        if ($locale) {
            \Locale::setDefault($locale);
        }

        return Intl::getRegionBundle()->getCountryName($isoCode);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'country_extension';
    }
}
