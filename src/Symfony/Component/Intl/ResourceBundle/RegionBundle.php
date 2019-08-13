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

use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Provider\LocaleDataProvider;
use Symfony\Component\Intl\Data\Provider\RegionDataProvider;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Default implementation of {@link RegionBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal to be removed in 5.0.
 */
class RegionBundle extends RegionDataProvider implements RegionBundleInterface
{
    private $localeProvider;

    public function __construct(string $path, BundleEntryReaderInterface $reader, LocaleDataProvider $localeProvider)
    {
        parent::__construct($path, $reader);

        $this->localeProvider = $localeProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryName($country, $displayLocale = null)
    {
        try {
            return $this->getName($country, $displayLocale);
        } catch (MissingResourceException $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryNames($displayLocale = null)
    {
        try {
            return $this->getNames($displayLocale);
        } catch (MissingResourceException $e) {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        try {
            return $this->localeProvider->getLocales();
        } catch (MissingResourceException $e) {
            return [];
        }
    }
}
