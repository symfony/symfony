<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Exception;

use Psr\Cache\CacheException as Psr6CacheInterface;
use Psr\SimpleCache\CacheException as SimpleCacheInterface;

class CacheException extends \Exception implements Psr6CacheInterface, SimpleCacheInterface
{
}
