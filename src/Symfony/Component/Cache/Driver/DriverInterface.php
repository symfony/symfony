<?php

namespace Symfony\Component\Cache\Driver;

use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface DriverInterface
{
    /**
     * @param DataInterface $data
     *
     * @return DataInterface
     */
    public function fetch(DataInterface $data);

    /**
     * @param DataInterface $data
     *
     * @return DataInterface
     */
    public function store(DataInterface $data);

    /**
     * @param KeyCollection $data
     *
     * @return KeyCollection
     */
    public function delete(KeyCollection $data);

    /**
     * @return boolean
     */
    public function flush();
}
