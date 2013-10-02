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

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Exception\NoSuchEntryException;
use Symfony\Component\Intl\Exception\OutOfBoundsException;
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
    public function readEntry($path, $locale, array $indices, $fallback = true)
    {
        $entry = null;
        $isMultiValued = false;
        $readSucceeded = false;
        $exception = null;
        $currentLocale = $locale;
        $testedLocales = array();

        while (null !== $currentLocale) {
            try {
                $data = $this->reader->read($path, $currentLocale);
                $currentEntry = RecursiveArrayAccess::get($data, $indices);
                $readSucceeded = true;

                $isCurrentTraversable = $currentEntry instanceof \Traversable;
                $isCurrentMultiValued = $isCurrentTraversable || is_array($currentEntry);

                // Return immediately if fallback is disabled or we are dealing
                // with a scalar non-null entry
                if (!$fallback || (!$isCurrentMultiValued && null !== $currentEntry)) {
                    return $currentEntry;
                }

                // =========================================================
                // Fallback is enabled, entry is either multi-valued or NULL
                // =========================================================

                // If entry is multi-valued, convert to array
                if ($isCurrentTraversable) {
                    $currentEntry = iterator_to_array($currentEntry);
                }

                // If previously read entry was multi-valued too, merge them
                if ($isCurrentMultiValued && $isMultiValued) {
                    $currentEntry = array_merge($currentEntry, $entry);
                }

                // Keep the previous entry if the current entry is NULL
                if (null !== $currentEntry) {
                    $entry = $currentEntry;
                }

                // If this or the previous entry was multi-valued, we are dealing
                // with a merged, multi-valued entry now
                $isMultiValued = $isMultiValued || $isCurrentMultiValued;
            } catch (OutOfBoundsException $e) {
                // Remember exception and rethrow if we cannot find anything in
                // the fallback locales either
                if (null === $exception) {
                    $exception = $e;
                }
            }

            // Remember which locales we tried
            $testedLocales[] = $currentLocale.'.res';

            // Go to fallback locale
            $currentLocale = Intl::getFallbackLocale($currentLocale);
        }

        // Multi-valued entry was merged
        if ($isMultiValued) {
            return $entry;
        }

        // Entry is still NULL, but no read error occurred
        if ($readSucceeded) {
            return $entry;
        }

        // Entry is still NULL, read error occurred. Throw an exception
        // containing the detailed path and locale
        $errorMessage = sprintf(
            'Error while reading the indices [%s] in "%s/%s.res": %s.',
            implode('][', $indices),
            $path,
            $locale,
            $exception->getMessage()
        );

        // Append fallback locales, if any
        if (count($testedLocales) > 1) {
            // Remove original locale
            array_shift($testedLocales);

            $errorMessage .= sprintf(
                ' The index could also be found in neither of the fallback locales: "%s".',
                implode('", "', $testedLocales)
            );
        }

        throw new NoSuchEntryException($errorMessage, 0, $exception);
    }
}
