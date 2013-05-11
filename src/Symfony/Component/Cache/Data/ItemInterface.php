<?php

namespace Symfony\Component\Cache\Data;

use Symfony\Component\Cache\Psr\CacheItemInterface;

/**
 * Interface for items pushed or retrieved from the cache.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface ItemInterface extends CacheItemInterface, DataInterface
{
}
