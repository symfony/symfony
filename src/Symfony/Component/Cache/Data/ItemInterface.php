<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
