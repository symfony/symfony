<?php

namespace Symfony\Bridge\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides translate country in ISO 3166-1 alpha 2 to country name.
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
        if ($isoCode === null) {
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