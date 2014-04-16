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

use Symfony\Component\Intl\ResourceBundle\Util\RecursiveArrayAccess;

/**
 * A structured reader wrapping an existing resource bundle reader.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see StructuredResourceBundleBundleReaderInterface
 */
class StructuredBundleReader implements StructuredBundleReaderInterface
{
    /**
     * @var BundleReaderInterface
     */
    private $reader;

    /**
     * Creates an entry reader based on the given resource bundle reader.
     *
     * @param BundleReaderInterface $reader A resource bundle reader to use.
     */
    public function __construct(BundleReaderInterface $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        return $this->reader->read($path, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales($path)
    {
        return $this->reader->getLocales($path);
    }

    /**
     * {@inheritdoc}
     */
    public function readEntry($path, $locale, array $indices, $fallback = true)
    {
        $data = $this->reader->read($path, $locale);

        $entry = RecursiveArrayAccess::get($data, $indices);
        $multivalued = is_array($entry) || $entry instanceof \Traversable;

        if (!($fallback && (null === $entry || $multivalued))) {
            return $entry;
        }

        if (null !== ($fallbackLocale = $this->getFallbackLocale($locale))) {
            $parentEntry = $this->readEntry($path, $fallbackLocale, $indices, true);

            if ($entry || $parentEntry) {
                $multivalued = $multivalued || is_array($parentEntry) || $parentEntry instanceof \Traversable;

                if ($multivalued) {
                    if ($entry instanceof \Traversable) {
                        $entry = iterator_to_array($entry);
                    }

                    if ($parentEntry instanceof \Traversable) {
                        $parentEntry = iterator_to_array($parentEntry);
                    }

                    $entry = array_merge(
                        $parentEntry ?: array(),
                        $entry ?: array()
                    );
                } else {
                    $entry = null === $entry ? $parentEntry : $entry;
                }
            }
        }

        return $entry;
    }

    /**
     * Returns the fallback locale for a given locale, if any
     *
     * @param string $locale The locale to find the fallback for.
     *
     * @return string|null The fallback locale, or null if no parent exists
     */
    private function getFallbackLocale($locale)
    {
        if (false === $pos = strrpos($locale, '_')) {
            return;
        }

        return substr($locale, 0, $pos);
    }
}
