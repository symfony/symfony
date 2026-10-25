<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl;

use Symfony\Component\Intl\Data\Bundle\Reader\BufferedBundleReader;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\PhpBundleReader;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
abstract class ResourceBundle
{
    private static BundleEntryReader $entryReader;

    /**
     * @var array<string, array<string, int>>
     */
    private static array $indexes = [];

    abstract protected static function getPath(): string;

    /**
     * Reads an entry from a resource bundle.
     *
     * @see BundleEntryReaderInterface::readEntry()
     *
     * @param (string|int)[] $indices         The indices to read from the bundle
     * @param string|null    $locale          The locale to read
     * @param bool           $fallback        Whether to merge the value with the value from
     *                                        the fallback locale (e.g. "en" for "en_GB").
     *                                        Only applicable if the result is multivalued
     *                                        (i.e. array or \ArrayAccess) or cannot be found
     *                                        in the requested locale.
     * @param bool           $defaultFallback Whether to fall back to the default locale (e.g. "en" for "fr") once the parents of the locale are exhausted
     *
     * @return mixed returns an array or {@link \ArrayAccess} instance for
     *               complex data and a scalar value for simple data
     */
    final protected static function readEntry(array $indices, ?string $locale = null, bool $fallback = true, bool $defaultFallback = true): mixed
    {
        return self::getEntryReader()->readEntry(static::getPath(), $locale ?? \Locale::getDefault(), $indices, $fallback, $defaultFallback);
    }

    /**
     * Reads the value of a code from the "Names" entry of a resource bundle.
     *
     * The "Names" entry stores each value at the position of its code in the "Index" entry of the "meta" bundle.
     *
     * @param bool $defaultFallback Whether to fall back to the default locale (e.g. "en" for "fr") once the parents of the locale are exhausted
     *
     * @throws MissingResourceException if the code has no value for the locale
     */
    final protected static function readName(string $code, ?string $locale = null, bool $defaultFallback = true): mixed
    {
        return self::readEntry(['Names', self::getPosition($code)], $locale, true, $defaultFallback);
    }

    /**
     * Reads the "Names" entry of a resource bundle, indexed by code.
     *
     * @param bool $defaultFallback Whether to fall back to the default locale (e.g. "en" for "fr") once the parents of the locale are exhausted
     *
     * @return array<string, mixed>
     *
     * @throws MissingResourceException if the locale has no names
     */
    final protected static function readNames(?string $locale = null, bool $defaultFallback = true): array
    {
        $codes = array_keys(self::getIndex());
        $names = [];

        foreach (self::readEntry(['Names'], $locale, true, $defaultFallback) as $position => $name) {
            $names[$codes[$position]] = $name;
        }

        return $names;
    }

    /**
     * Returns the position of a code in the "Index" entry of the "meta" bundle.
     *
     * @throws MissingResourceException if the code does not exist
     */
    final protected static function getPosition(string $code): int
    {
        return self::getIndex()[$code] ?? throw new MissingResourceException(\sprintf('The code "%s" does not exist in "%s".', $code, static::getPath()));
    }

    final protected static function asort(iterable $list, ?string $locale = null): array
    {
        if ($list instanceof \Traversable) {
            $list = iterator_to_array($list);
        }

        $collator = new \Collator($locale ?? \Locale::getDefault());
        $collator->asort($list);

        return $list;
    }

    private static function getEntryReader(): BundleEntryReader
    {
        if (!isset(self::$entryReader)) {
            self::$entryReader = new BundleEntryReader(new BufferedBundleReader(
                new PhpBundleReader(),
                Intl::BUFFER_SIZE
            ));

            $localeMeta = self::$entryReader->read(Intl::getDataDirectory().'/'.Intl::LOCALE_DIR, 'meta');
            self::$entryReader->setLocaleAliases($localeMeta['Aliases']);
            self::$entryReader->setLocaleParents($localeMeta['Parents']);
        }

        return self::$entryReader;
    }

    /**
     * @return array<string, int>
     */
    private static function getIndex(): array
    {
        return self::$indexes[static::getPath()] ??= self::readEntry(['Index'], 'meta');
    }

    private function __construct()
    {
    }
}
