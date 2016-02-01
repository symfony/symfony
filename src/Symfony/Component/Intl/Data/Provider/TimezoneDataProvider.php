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
 * Data provider for timezone-related data.
 *
 * @internal
 */
class TimezoneDataProvider
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var BundleEntryReaderInterface
     */
    private $reader;

    /**
     * Creates a data provider that reads timezone-related data from a
     * resource bundle.
     *
     * @param string                     $path   The path to the resource bundle.
     * @param BundleEntryReaderInterface $reader The reader for reading the resource bundle.
     */
    public function __construct($path, BundleEntryReaderInterface $reader)
    {
        $this->path = $path;
        $this->reader = $reader;
    }

    public function getIDs()
    {
        return $this->reader->readEntry($this->path, 'meta', array('Timezones'));
    }

    public function getName($zoneID, $displayLocale = null)
    {
        if (null === $displayLocale) {
            $displayLocale = Locale::getDefault();
        }

        return $this->reader->readEntry($this->path, $displayLocale, array('Names', $zoneID));
    }

    public function getNames($displayLocale = null)
    {
        if (null === $displayLocale) {
            $displayLocale = Locale::getDefault();
        }

        $names = $this->reader->readEntry($this->path, $displayLocale, array('Names'));

        if ($names instanceof \Traversable) {
            $names = iterator_to_array($names);
        }

        // Sorting by value cannot be done during bundle generation, because
        // binary bundles are always sorted by keys
        $collator = new \Collator($displayLocale);
        $collator->asort($names);

        return $names;
    }
}
