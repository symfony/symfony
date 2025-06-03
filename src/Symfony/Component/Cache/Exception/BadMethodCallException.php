<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Exception;

use Psr\Cache\CacheException as Psr6CacheInterface;
use Psr\SimpleCache\CacheException as SimpleCacheInterface;

if (interface_exists(SimpleCacheInterface::class)) {
    class BadMethodCallException extends \BadMethodCallException implements Psr6CacheInterface, SimpleCacheInterface
    {
    }
} else {
    class BadMethodCallException extends \BadMethodCallException implements Psr6CacheInterface
    {
    }
}
