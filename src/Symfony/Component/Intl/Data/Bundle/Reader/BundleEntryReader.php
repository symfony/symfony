<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Reader;

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Exception\OutOfBoundsException;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Data\Util\RecursiveArrayAccess;

/**
 * Default implementation of {@link BundleEntryReaderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see BundleEntryReaderInterface
 *
 * @internal
 */
class BundleEntryReader implements BundleEntryReaderInterface
{
    private $reader;

    /**
     * A mapping of locale aliases to locales.
     */
    private $localeAliases = array();

    /**
     * Creates an entry reader based on the given resource bundle reader.
     */
    public function __construct(BundleReaderInterface $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Stores a mapping of locale aliases to locales.
     *
     * This mapping is used when reading entries and merging them with their
     * fallback locales. If an entry is read for a locale alias (e.g. "mo")
     * that points to a locale with a fallback locale ("ro_MD"), the reader
     * can continue at the correct fallback locale ("ro").
     *
     * @param array $localeAliases A mapping of locale aliases to locales
     */
    public function setLocaleAliases($localeAliases)
    {
        $this->localeAliases = $localeAliases;
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
            // Resolve any aliases to their target locales
            if (isset($this->localeAliases[$currentLocale])) {
                $currentLocale = $this->localeAliases[$currentLocale];
            }

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
            } catch (ResourceBundleNotFoundException $e) {
                // Continue if there is a fallback locale for the current
                // locale
                $exception = $e;
            } catch (OutOfBoundsException $e) {
                // Remember exception and rethrow if we cannot find anything in
                // the fallback locales either
                $exception = $e;
            }

            // Remember which locales we tried
            $testedLocales[] = $currentLocale;

            // Check whether fallback is allowed
            if (!$fallback) {
                break;
            }

            // Then determine fallback locale
            $currentLocale = Locale::getFallback($currentLocale);
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
            'Couldn\'t read the indices [%s] for the locale "%s" in "%s".',
            implode('][', $indices),
            $locale,
            $path
        );

        // Append fallback locales, if any
        if (count($testedLocales) > 1) {
            // Remove original locale
            array_shift($testedLocales);

            $errorMessage .= sprintf(
                ' The indices also couldn\'t be found for the fallback locale(s) "%s".',
                implode('", "', $testedLocales)
            );
        }

        throw new MissingResourceException($errorMessage, 0, $exception);
    }
}
