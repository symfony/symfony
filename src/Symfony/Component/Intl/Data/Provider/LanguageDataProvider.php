<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Provider;

use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;

/**
 * Data provider for language-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LanguageDataProvider
{
    private $path;
    private $reader;

    /**
     * Creates a data provider that reads locale-related data from .res files.
     *
     * @param string                     $path   The path to the directory containing the .res files
     * @param BundleEntryReaderInterface $reader The reader for reading the .res files
     */
    public function __construct(string $path, BundleEntryReaderInterface $reader)
    {
        $this->path = $path;
        $this->reader = $reader;
    }

    public function getLanguages()
    {
        return $this->reader->readEntry($this->path, 'meta', array('Languages'));
    }

    public function getAliases()
    {
        return $this->reader->readEntry($this->path, 'root', array('Aliases'));
    }

    public function getName($language, $displayLocale = null)
    {
        if (null === $displayLocale) {
            $displayLocale = Locale::getDefault();
        }

        return $this->reader->readEntry($this->path, $displayLocale, array('Names', $language));
    }

    public function getNames($displayLocale = null)
    {
        if (null === $displayLocale) {
            $displayLocale = Locale::getDefault();
        }

        $languages = $this->reader->readEntry($this->path, $displayLocale, array('Names'));

        if ($languages instanceof \Traversable) {
            $languages = iterator_to_array($languages);
        }

        $collator = new \Collator($displayLocale);
        $collator->asort($languages);

        return $languages;
    }

    public function getAlpha3Code($language)
    {
        return $this->reader->readEntry($this->path, 'meta', array('Alpha2ToAlpha3', $language));
    }
}
