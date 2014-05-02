<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Adapter;

/**
 * Collection to store prioritized Finder adapters.
 *
 * @author Hugo Hamon <hugo.hamon@sensiolabs.com>
 */
class AdapterCollection implements AdapterCollectionInterface
{
    private $adapters = array();

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return array_values(array_map(function (array $adapter) {
            return $adapter['adapter'];
        }, $this->adapters));
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->adapters = array();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function add(AdapterInterface $adapter, $priority = 0)
    {
        $this->adapters[$adapter->getName()] = array(
            'adapter'  => $adapter,
            'priority' => $priority,
            'selected' => false,
        );

        return $this->sortAdapters();
    }

    /**
     * {@inheritdoc}
     */
    public function useBestAdapter()
    {
        return $this->resetAdapterSelection()->sortAdapters();
    }

    /**
     * {@inheritdoc}
     */
    public function setAdapter($name)
    {
        if (!isset($this->adapters[$name])) {
            throw new \InvalidArgumentException(sprintf('Adapter "%s" does not exist.', $name));
        }

        $this->resetAdapterSelection();
        $this->adapters[$name]['selected'] = true;

        return $this->sortAdapters();
    }

    /**
     * Sort adapters by priority.
     *
     * The selected adapter wins, otherwise the highest priority wins.
     *
     * @return AdapterCollection The current AdapterCollection instance
     */
    private function sortAdapters()
    {
        uasort($this->adapters, function (array $a, array $b) {
            if ($a['selected'] || $b['selected']) {
                return $a['selected'] ? -1 : 1;
            }

            return $a['priority'] > $b['priority'] ? -1 : 1;
        });

        return $this;
    }

    /**
     * Unselects all adapters.
     *
     * @return AdapterCollection The current AdapterCollection instance
     */
    private function resetAdapterSelection()
    {
        $this->adapters = array_map(function (array $properties) {
            $properties['selected'] = false;

            return $properties;
        }, $this->adapters);

        return $this;
    }
}
