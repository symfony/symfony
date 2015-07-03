<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Header;

/**
 * Represents an Accept-* header.
 *
 * An accept header is compound with a list of items,
 * sorted by descending quality.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Accept
{
    /**
     * @var AcceptItem[]
     */
    private $items = array();

    /**
     * @var bool
     */
    private $sorted = true;

    /**
     * Constructor.
     *
     * @param AcceptItem[] $items
     */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * Builds an AcceptHeader instance from a string.
     *
     * @param string $headerValue
     *
     * @return Accept
     */
    public static function fromString($headerValue)
    {
        $index = 0;

        return new static(array_map(function ($itemValue) use (&$index) {
            $item = AcceptItem::fromString($itemValue);
            $item->setIndex($index++);

            return $item;
        }, preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $headerValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)));
    }

    /**
     * Returns header value's string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return implode(',', $this->items);
    }

    /**
     * Tests if header has given value.
     *
     * @param string $value
     *
     * @return bool
     */
    public function has($value)
    {
        return isset($this->items[$value]);
    }

    /**
     * Returns given value's item, if exists.
     *
     * @param string $value
     *
     * @return AcceptItem|null
     */
    public function get($value)
    {
        return isset($this->items[$value]) ? $this->items[$value] : null;
    }

    /**
     * Adds an item.
     *
     * @param AcceptItem $item
     *
     * @return Accept
     */
    public function add(AcceptItem $item)
    {
        $this->items[$item->getValue()] = $item;
        $this->sorted = false;

        return $this;
    }

    /**
     * Returns all items.
     *
     * @return AcceptItem[]
     */
    public function all()
    {
        $this->sort();

        return $this->items;
    }

    /**
     * Filters items on their value using given regex.
     *
     * @param string $pattern
     *
     * @return Accept
     */
    public function filter($pattern)
    {
        return new self(array_filter($this->items, function (AcceptItem $item) use ($pattern) {
            return preg_match($pattern, $item->getValue());
        }));
    }

    /**
     * Returns first item.
     *
     * @return AcceptItem|null
     */
    public function first()
    {
        $this->sort();

        return !empty($this->items) ? reset($this->items) : null;
    }

    /**
     * Sorts items by descending quality.
     */
    private function sort()
    {
        if ($this->sorted) {
            return;
        }

        uasort($this->items, function (AcceptItem $a, AcceptItem $b) {
            $qA = $a->getQuality();
            $qB = $b->getQuality();

            if ($qA === $qB) {
                return $a->getIndex() > $b->getIndex() ? 1 : -1;
            }

            return $qA > $qB ? -1 : 1;
        });

        $this->sorted = true;
    }
}
