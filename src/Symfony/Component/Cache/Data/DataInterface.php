<?php

namespace Symfony\Component\Cache\Data;

/**
 * Common interface for items and collections.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface DataInterface
{
    /**
     * Tests if data is valid (ie. it can be cached).
     *
     * @return boolean
     */
    public function isValid();

    /**
     * Tests if item has been cached.
     *
     * @return boolean
     */
    public function isCached();

    /**
     * Tests if data is a collection.
     *
     * @return boolean
     */
    public function isCollection();
}
