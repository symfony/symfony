<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Icu;

use Symfony\Component\Intl\ResourceBundle\LocaleBundle;
use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface;

/**
 * An ICU-specific implementation of {@link \Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface}.
 *
 * This class normalizes the data of the ICU .res files to satisfy the contract
 * defined in {@link \Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuLocaleBundle extends LocaleBundle
{
    private $stubbed;

    public function __construct(StructuredBundleReaderInterface $reader)
    {
        $this->stubbed = IcuData::isStubbed();

        parent::__construct(realpath(IcuData::getResourceDirectory().'/locales'), $reader);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales()
    {
        return $this->stubbed ? array('en') : $this->readEntry('misc', array('Locales'));
    }

    /**
     * {@inheritdoc}
     */
    public function getLocaleNames($locale = null)
    {
        $locales = parent::getLocaleNames($locale);

        if ($this->stubbed) {
            return $locales;
        }

        if (null === $locale) {
            $locale = \Locale::getDefault();
        }

        $collator = new \Collator($locale);
        $collator->asort($locales);

        return $locales;
    }
}
