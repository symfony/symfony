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

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface;

/**
 * Base class for {@link ResourceBundleInterface} implementations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractBundle implements ResourceBundleInterface
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var StructuredBundleReaderInterface
     */
    private $reader;

    /**
     * Creates a bundle at the given path using the given reader for reading
     * bundle entries.
     *
     * @param string                          $path   The path to the bundle.
     * @param StructuredBundleReaderInterface $reader The reader for reading
     *                                                the bundle.
     */
    public function __construct($path, StructuredBundleReaderInterface $reader)
    {
        $this->path = $path;
        $this->reader = $reader;
    }

    /**
     * Proxy method for {@link BundleEntryReaderInterface#read}.
     */
    protected function read($locale)
    {
        return $this->reader->read($this->path, $locale);
    }

    /**
     * Proxy method for {@link BundleEntryReaderInterface#readEntry}.
     */
    protected function readEntry($locale, array $indices, $fallback = true)
    {
        return $this->reader->readEntry($this->path, $locale, $indices, $fallback);
    }
}
