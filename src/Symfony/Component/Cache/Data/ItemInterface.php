<?php

namespace Symfony\Component\Cache\Data;

/**
 * Interface for items pushed or retrieved from the cache.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface ItemInterface extends DataInterface
{
    /**
     * Returns item unique key.
     *
     * @return string
     */
    public function getKey();

    /**
     * Returns item data.
     *
     * @return mixed
     */
    public function getData();
}
