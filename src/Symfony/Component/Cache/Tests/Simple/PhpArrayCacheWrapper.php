<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Symfony\Component\Cache\Simple\PhpArrayCache;

class PhpArrayCacheWrapper extends PhpArrayCache
{
    protected $data = [];

    public function set($key, $value, $ttl = null)
    {
        (\Closure::bind(function () use ($key, $value) {
            $this->data[$key] = $value;
            $this->warmUp($this->data);
            list($this->keys, $this->values) = eval(substr(file_get_contents($this->file), 6));
        }, $this, PhpArrayCache::class))();

        return true;
    }

    public function setMultiple($values, $ttl = null)
    {
        if (!\is_array($values) && !$values instanceof \Traversable) {
            return parent::setMultiple($values, $ttl);
        }
        (\Closure::bind(function () use ($values) {
            foreach ($values as $key => $value) {
                $this->data[$key] = $value;
            }
            $this->warmUp($this->data);
            list($this->keys, $this->values) = eval(substr(file_get_contents($this->file), 6));
        }, $this, PhpArrayCache::class))();

        return true;
    }
}
