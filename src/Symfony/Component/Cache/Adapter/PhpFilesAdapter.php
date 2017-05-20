<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Traits\PhpFilesTrait;

class PhpFilesAdapter extends AbstractAdapter
{
    use PhpFilesTrait;

    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
    {
        if (!static::isSupported()) {
            throw new CacheException('OPcache is not enabled');
        }
        parent::__construct('', $defaultLifetime);
        $this->init($namespace, $directory);

        $e = new \Exception();
        $this->includeHandler = function () use ($e) { throw $e; };
    }
}
