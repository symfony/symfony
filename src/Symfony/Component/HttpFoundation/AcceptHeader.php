<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Represents an Accept-* header.
 *
 * An accept header is compound with a list of items,
 * sorted by descending quality.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeader
{
    /**
     * @var AcceptHeaderItem[]
     */
    private $items = array();

    /**
     * @var bool
     */
    private $sorted = true;

    /**
     * Builds an AcceptHeader instance from a string.
     *
     * @param string $headerValue
     *
     * @return AcceptHeader
     */
    public static function fromString($headerValue)
    {
        return new static(array_map(function ($itemValue) {
            return AcceptHeaderItem::fromString($itemValue);
        }, preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/', $headerValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)));
    }

    /**
     * Constructor.
     *
     * @param array $items
     */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * Returns header  value's string representation.
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
     * @return AcceptHeaderItem|null
     */
    public function get($value)
    {
        return isset($this->items[$value]) ? $this->items[$value] : null;
    }

    /**
     * Adds an item.
     *
     * @param AcceptHeaderItem $item
     *
     * @return AcceptHeader
     */
    public function add(AcceptHeaderItem $item)
    {
        $this->items[$item->getValue()] = $item;
        $this->sorted = false;

        return $this;
    }

    /**
     * Returns all items.
     *
     * @return AcceptHeaderItem[]
     */
    public function all()
    {
        $this->ensureSorted();

        return $this->items;
    }

    /**
     * Filters items on their value using given regex.
     *
     * @param string $pattern
     *
     * @return AcceptHeader
     */
    public function filter($pattern)
    {
        return new static(array_filter($this->items, function (AcceptHeaderItem $item) use ($pattern) {
            return preg_match($pattern, $item->getValue());
        }));
    }

    /**
     * Returns first item.
     *
     * @return AcceptHeaderItem|null
     */
    public function first()
    {
        $this->ensureSorted();

        return !empty($this->items) ? current($this->items) : null;
    }

    /**
     * Ensures items are sorted by descending quality
     */
    private function ensureSorted()
    {
        if (!$this->sorted) {
            $this->items = $this->sort($this->items);
        }
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function sort(array $array)
    {
        if (count($array) < 2) {
            return $array;
        }

        $middle = count($array) / 2;

        return $this->merge(
            $this->sort(array_slice($array, 0, $middle)),
            $this->sort(array_slice($array, $middle))
        );
    }

    private function merge(array $left, array $right)
    {
        $array = array();
        while (count($left) + count($right) > 0) {
            if (empty($left)) {
                $item = array_splice($right, 0, 1);
            } elseif(empty($right)) {
                $item = array_splice($left, 0, 1);
            } else {
                $item = current($right)->getQuality() > current($left)->getQuality() ? array_splice($right, 0, 1) : array_splice($left, 0, 1);
            }
            list($key, $value) = each($item);
            $array[$key] = $value;
        }

        return $array;
    }
}
