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

use Symfony\Component\Routing\RouteCollection;

/**
 * Prefix tree of routes preserving routes order.
 *
 * @author Frank de Jonge <info@frankdejonge.nl>
 *
 * @internal
 */
class StaticPrefixCollection
{
    private $prefix;
    private $staticPrefix;
    private $matchStart = 0;

    /**
     * @var string[]
     */
    private $prefixes = array();

    /**
     * @var array[]|self[]
     */
    private $items = array();

    public function __construct(string $prefix = '/', string $staticPrefix = '/')
    {
        $this->prefix = $prefix;
        $this->staticPrefix = $staticPrefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return array[]|self[]
     */
    public function getRoutes(): array
    {
        return $this->items;
    }

    /**
     * Adds a route to a group.
     *
     * @param array|self $route
     */
    public function addRoute(string $prefix, $route)
    {
        $this->guardAgainstAddingNotAcceptedRoutes($prefix);
        list($prefix, $staticPrefix) = $this->detectCommonPrefix($prefix, $prefix) ?: array(rtrim($prefix, '/') ?: '/', '/');

        if ($this->staticPrefix === $staticPrefix) {
            // When a prefix is exactly the same as the base we move up the match start position.
            // This is needed because otherwise routes that come afterwards have higher precedence
            // than a possible regular expression, which goes against the input order sorting.
            $this->prefixes[] = $prefix;
            $this->items[] = $route;
            $this->matchStart = count($this->items);

            return;
        }

        for ($i = $this->matchStart; $i < \count($this->items); ++$i) {
            $item = $this->items[$i];

            if ($item instanceof self && $item->accepts($prefix)) {
                $item->addRoute($prefix, $route);

                return;
            }

            if ($group = $this->groupWithItem($i, $prefix, $route)) {
                $this->prefixes[$i] = $group->getPrefix();
                $this->items[$i] = $group;

                return;
            }
        }

        // No optimised case was found, in this case we simple add the route for possible
        // grouping when new routes are added.
        $this->prefixes[] = $prefix;
        $this->items[] = $route;
    }

    /**
     * Linearizes back a set of nested routes into a collection.
     */
    public function populateCollection(RouteCollection $routes): RouteCollection
    {
        foreach ($this->items as $route) {
            if ($route instanceof self) {
                $route->populateCollection($routes);
            } else {
                $routes->add(...$route);
            }
        }

        return $routes;
    }

    /**
     * Tries to combine a route with another route or group.
     */
    private function groupWithItem(int $i, string $prefix, $route): ?self
    {
        if (!$commonPrefix = $this->detectCommonPrefix($prefix, $this->prefixes[$i])) {
            return null;
        }

        $child = new self(...$commonPrefix);
        $item = $this->items[$i];

        if ($item instanceof self) {
            $child->prefixes = array($commonPrefix[0]);
            $child->items = array($item);
        } else {
            $child->addRoute($this->prefixes[$i], $item);
        }

        $child->addRoute($prefix, $route);

        return $child;
    }

    /**
     * Checks whether a prefix can be contained within the group.
     */
    private function accepts(string $prefix): bool
    {
        return '' === $this->prefix || 0 === strpos($prefix, $this->prefix);
    }

    /**
     * Detects whether there's a common prefix relative to the group prefix and returns it.
     *
     * @return null|array A common prefix, longer than the base/group prefix, or null when none available
     */
    private function detectCommonPrefix(string $prefix, string $anotherPrefix): ?array
    {
        $baseLength = strlen($this->prefix);
        $end = min(strlen($prefix), strlen($anotherPrefix));
        $staticLength = null;

        for ($i = $baseLength; $i < $end && $prefix[$i] === $anotherPrefix[$i]; ++$i) {
            if ('(' === $prefix[$i]) {
                $staticLength = $staticLength ?? $i;
                for ($j = 1 + $i, $n = 1; $j < $end && 0 < $n; ++$j) {
                    if ($prefix[$j] !== $anotherPrefix[$j]) {
                        break 2;
                    }
                    if ('(' === $prefix[$j]) {
                        ++$n;
                    } elseif (')' === $prefix[$j]) {
                        --$n;
                    } elseif ('\\' === $prefix[$j] && (++$j === $end || $prefix[$j] !== $anotherPrefix[$j])) {
                        --$j;
                        break;
                    }
                }
                if (0 < $n) {
                    break;
                }
                $i = $j;
            } elseif ('\\' === $prefix[$i] && (++$i === $end || $prefix[$i] !== $anotherPrefix[$i])) {
                --$i;
                break;
            }
        }

        $staticLength = $staticLength ?? $i;
        $commonPrefix = rtrim(substr($prefix, 0, $i), '/');

        if (strlen($commonPrefix) > $baseLength) {
            return array($commonPrefix, rtrim(substr($prefix, 0, $staticLength), '/') ?: '/');
        }

        return null;
    }

    /**
     * Guards against adding incompatible prefixes in a group.
     *
     * @throws \LogicException when a prefix does not belong in a group
     */
    private function guardAgainstAddingNotAcceptedRoutes(string $prefix): void
    {
        if (!$this->accepts($prefix)) {
            $message = sprintf('Could not add route with prefix %s to collection with prefix %s', $prefix, $this->prefix);

            throw new \LogicException($message);
        }
    }
}
