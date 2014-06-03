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
 * Interface for Finder adapters collections.
 *
 * @author Hugo Hamon <hugo.hamon@sensiolabs.com>
 */
interface AdapterCollectionInterface
{
    /**
     * Returns registered adapters ordered by priority without extra information.
     *
     * @return AdapterInterface[]
     */
    public function all();

    /**
     * Clears all adapters registered in the collection.
     *
     * @return AdapterCollection The current AdapterCollection instance
     */
    public function clear();

    /**
     * Registers a finder engine implementation.
     *
     * @param AdapterInterface $adapter  An adapter instance
     * @param int              $priority Highest is selected first
     *
     * @return AdapterCollection The current AdapterCollection instance
     */
    public function add(AdapterInterface $adapter, $priority = 0);

    /**
     * Sets the selected adapter to the best one according to the current platform the code is run on.
     *
     * @return AdapterCollection The current AdapterCollection instance
     */
    public function useBestAdapter();

    /**
     * Selects the adapter to use.
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException
     *
     * @return AdapterCollection The current AdapterCollection instance
     */
    public function setAdapter($name);
}
