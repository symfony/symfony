<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\PhpFilesTrait;

class PhpFilesCache extends AbstractCache implements PruneableInterface
{
    use PhpFilesTrait;

    /**
     * @throws CacheException if OPcache is not enabled
     */
    public function __construct(string $namespace = '', int $defaultLifetime = 0, string $directory = null)
    {
        if (!static::isSupported()) {
            throw new CacheException('OPcache is not enabled');
        }
        parent::__construct('', $defaultLifetime);
        $this->init($namespace, $directory);

        $e = new \Exception();
        $this->includeHandler = function () use ($e) { throw $e; };
        $this->zendDetectUnicode = filter_var(ini_get('zend.detect_unicode'), FILTER_VALIDATE_BOOLEAN);
    }
}
