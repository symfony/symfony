<?php

namespace Symfony\Component\Cache\Driver;

use Symfony\Component\Cache\Psr\CacheInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface DriverInterface extends CacheInterface
{
    /**
     * Returns driver name.
     *
     * @return string
     */
    public function getName();
}
