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
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class DumperPrefixCollection extends DumperCollection
{
    /**
     * @var string
     */
    private $prefix = '';

    /**
     * Returns the prefix.
     *
     * @return string The prefix
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Sets the prefix.
     *
     * @param string $prefix The prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Adds a route in the tree.
     *
     * @param DumperRoute $route The route
     *
     * @return DumperPrefixCollection The node the route was added to
     *
     * @throws \LogicException
     */
    public function addPrefixRoute(DumperRoute $route)
    {
        $prefix = $route->getRoute()->compile()->getStaticPrefix();

        // Same prefix, add to current leave
        if ($this->prefix === $prefix) {
            $this->add($route);

            return $this;
        }

        // Prefix starts with route's prefix
        if ('' === $this->prefix || 0 === strpos($prefix, $this->prefix)) {
            $collection = new DumperPrefixCollection();
            $collection->setPrefix(substr($prefix, 0, strlen($this->prefix)+1));
            $this->add($collection);

            return $collection->addPrefixRoute($route);
        }

        // No match, fallback to parent (recursively)

        if (null === $parent = $this->getParent()) {
            throw new \LogicException("The collection root must not have a prefix");
        }

        return $parent->addPrefixRoute($route);
    }

    /**
     * Merges nodes whose prefix ends with a slash
     *
     * Children of a node whose prefix ends with a slash are moved to the parent node
     */
    public function mergeSlashNodes()
    {
        $children = array();

        foreach ($this as $child) {
            if ($child instanceof self) {
                $child->mergeSlashNodes();
                if ('/' === substr($child->prefix, -1)) {
                    $children = array_merge($children, $child->all());
                } else {
                    $children[] = $child;
                }
            } else {
                $children[] = $child;
            }
        }

        $this->setAll($children);
    }
}
