<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Simple;

use Symphony\Component\Cache\Traits\MemcachedTrait;

class MemcachedCache extends AbstractCache
{
    use MemcachedTrait;

    protected $maxIdLength = 250;

    public function __construct(\Memcached $client, string $namespace = '', int $defaultLifetime = 0)
    {
        $this->init($client, $namespace, $defaultLifetime);
    }
}
