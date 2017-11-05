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

use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Locale;

/**
 * Data provider for script-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class ScriptDataProvider
{
    private $path;
    private $reader;

    /**
     * Creates a data provider that reads locale-related data from .res files.
     *
     * @param string                     $path   The path to the directory containing the .res files
     * @param BundleEntryReaderInterface $reader The reader for reading the .res files
     */
    public function __construct($path, BundleEntryReaderInterface $reader)
    {
        $this->path = $path;
        $this->reader = $reader;
    }

    public function getScripts()
    {
        return $this->reader->readEntry($this->path, 'meta', array('Scripts'));
    }

    public function getName($script, $displayLocale = null)
    {
        if (null === $displayLocale) {
            $displayLocale = Locale::getDefault();
        }

        return $this->reader->readEntry($this->path, $displayLocale, array('Names', $script));
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

        $collator = new \Collator($displayLocale);
        $collator->asort($names);

        return $names;
    }
}
