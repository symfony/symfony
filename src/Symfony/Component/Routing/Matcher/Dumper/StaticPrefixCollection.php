<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

/**
 * Prefix tree of routes preserving routes order.
 *
 * @author Frank de Jonge <info@frankdejonge.nl>
 *
 * @internal
 */
class StaticPrefixCollection
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var array[]|StaticPrefixCollection[]
     */
    private $items = array();

    /**
     * @var int
     */
    private $matchStart = 0;

    public function __construct($prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @return mixed[]|StaticPrefixCollection[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Adds a route to a group.
     *
     * @param string $prefix
     * @param mixed  $route
     */
    public function addRoute($prefix, $route)
    {
        $prefix = '/' === $prefix ? $prefix : rtrim($prefix, '/');
        $this->guardAgainstAddingNotAcceptedRoutes($prefix);

        if ($this->prefix === $prefix) {
            // When a prefix is exactly the same as the base we move up the match start position.
            // This is needed because otherwise routes that come afterwards have higher precedence
            // than a possible regular expression, which goes against the input order sorting.
            $this->items[] = array($prefix, $route);
            $this->matchStart = \count($this->items);

            return;
        }

        foreach ($this->items as $i => $item) {
            if ($i < $this->matchStart) {
                continue;
            }

            if ($item instanceof self && $item->accepts($prefix)) {
                $item->addRoute($prefix, $route);

                return;
            }

            $group = $this->groupWithItem($item, $prefix, $route);

            if ($group instanceof self) {
                $this->items[$i] = $group;

                return;
            }
        }

        // No optimised case was found, in this case we simple add the route for possible
        // grouping when new routes are added.
        $this->items[] = array($prefix, $route);
    }

    /**
     * Tries to combine a route with another route or group.
     *
     * @param StaticPrefixCollection|array $item
     * @param string                       $prefix
     * @param mixed                        $route
     *
     * @return null|StaticPrefixCollection
     */
    private function groupWithItem($item, $prefix, $route)
    {
        $itemPrefix = $item instanceof self ? $item->prefix : $item[0];
        $commonPrefix = $this->detectCommonPrefix($prefix, $itemPrefix);

        if (!$commonPrefix) {
            return;
        }

        $child = new self($commonPrefix);

        if ($item instanceof self) {
            $child->items = array($item);
        } else {
            $child->addRoute($item[0], $item[1]);
        }

        $child->addRoute($prefix, $route);

        return $child;
    }

    /**
     * Checks whether a prefix can be contained within the group.
     *
     * @param string $prefix
     *
     * @return bool Whether a prefix could belong in a given group
     */
    private function accepts($prefix)
    {
        return '' === $this->prefix || 0 === strpos($prefix, $this->prefix);
    }

    /**
     * Detects whether there's a common prefix relative to the group prefix and returns it.
     *
     * @param string $prefix
     * @param string $anotherPrefix
     *
     * @return false|string A common prefix, longer than the base/group prefix, or false when none available
     */
    private function detectCommonPrefix($prefix, $anotherPrefix)
    {
        $baseLength = \strlen($this->prefix);
        $commonLength = $baseLength;
        $end = min(\strlen($prefix), \strlen($anotherPrefix));

        for ($i = $baseLength; $i <= $end; ++$i) {
            if (substr($prefix, 0, $i) !== substr($anotherPrefix, 0, $i)) {
                break;
            }

            $commonLength = $i;
        }

        $commonPrefix = rtrim(substr($prefix, 0, $commonLength), '/');

        if (\strlen($commonPrefix) > $baseLength) {
            return $commonPrefix;
        }

        return false;
    }

    /**
     * Optimizes the tree by inlining items from groups with less than 3 items.
     */
    public function optimizeGroups()
    {
        $index = -1;

        while (isset($this->items[++$index])) {
            $item = $this->items[$index];

            if ($item instanceof self) {
                $item->optimizeGroups();

                // When a group contains only two items there's no reason to optimize because at minimum
                // the amount of prefix check is 2. In this case inline the group.
                if ($item->shouldBeInlined()) {
                    array_splice($this->items, $index, 1, $item->items);

                    // Lower index to pass through the same index again after optimizing.
                    // The first item of the replacements might be a group needing optimization.
                    --$index;
                }
            }
        }
    }

    private function shouldBeInlined()
    {
        if (\count($this->items) >= 3) {
            return false;
        }

        foreach ($this->items as $item) {
            if ($item instanceof self) {
                return true;
            }
        }

        foreach ($this->items as $item) {
            if (\is_array($item) && $item[0] === $this->prefix) {
                return false;
            }
        }

        return true;
    }

    /**
     * Guards against adding incompatible prefixes in a group.
     *
     * @param string $prefix
     *
     * @throws \LogicException when a prefix does not belong in a group
     */
    private function guardAgainstAddingNotAcceptedRoutes($prefix)
    {
        if (!$this->accepts($prefix)) {
            $message = sprintf('Could not add route with prefix %s to collection with prefix %s', $prefix, $this->prefix);

            throw new \LogicException($message);
        }
    }
}
