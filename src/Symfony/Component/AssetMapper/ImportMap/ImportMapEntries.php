<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

/**
 * Holds the collection of importmap entries defined in importmap.php.
 *
 * @template-implements \IteratorAggregate<ImportMapEntry>
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class ImportMapEntries implements \IteratorAggregate
{
    private array $entries = [];

    /**
     * @param ImportMapEntry[] $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }

    public function add(ImportMapEntry $entry): void
    {
        $this->entries[$entry->importName] = $entry;
    }

    public function has(string $importName): bool
    {
        return isset($this->entries[$importName]);
    }

    public function get(string $importName): ImportMapEntry
    {
        if (!$this->has($importName)) {
            throw new \InvalidArgumentException(sprintf('The importmap entry "%s" does not exist.', $importName));
        }

        return $this->entries[$importName];
    }

    /**
     * @return \Traversable<ImportMapEntry>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->entries));
    }

    public function remove(string $packageName): void
    {
        unset($this->entries[$packageName]);
    }
}
