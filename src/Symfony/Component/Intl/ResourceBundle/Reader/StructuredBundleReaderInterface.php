<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Reader;

/**
 * Reads individual entries of a resource file.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface StructuredBundleReaderInterface extends BundleReaderInterface
{
    /**
     * Reads an entry from a resource bundle.
     *
     * An entry can be selected from the resource bundle by passing the path
     * to that entry in the bundle. For example, if the bundle is structured
     * like this:
     *
     *     TopLevel
     *         NestedLevel
     *             Entry: Value
     *
     * Then the value can be read by calling:
     *
     *     $reader->readEntry('...', 'en', array('TopLevel', 'NestedLevel', 'Entry'));
     *
     * @param string   $path     The path to the resource bundle.
     * @param string   $locale   The locale to read.
     * @param string[] $indices  The indices to read from the bundle.
     * @param bool     $fallback Whether to merge the value with the value from
     *                           the fallback locale (e.g. "en" for "en_GB").
     *                           Only applicable if the result is multivalued
     *                           (i.e. array or \ArrayAccess) or cannot be found
     *                           in the requested locale.
     *
     * @return mixed Returns an array or {@link \ArrayAccess} instance for
     *               complex data, a scalar value for simple data and NULL
     *               if the given path could not be accessed.
     */
    public function readEntry($path, $locale, array $indices, $fallback = true);
}
