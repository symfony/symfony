<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

abstract class AbstractPhpFileCacheWarmer implements CacheWarmerInterface
{
    private $phpArrayFile;
    private $fallbackPool;

    /**
     * @param string                 $phpArrayFile The PHP file where metadata are cached
     * @param CacheItemPoolInterface $fallbackPool The pool where runtime-discovered metadata are cached
     */
    public function __construct($phpArrayFile, CacheItemPoolInterface $fallbackPool)
    {
        $this->phpArrayFile = $phpArrayFile;
        if (!$fallbackPool instanceof AdapterInterface) {
            $fallbackPool = new ProxyAdapter($fallbackPool);
        }
        $this->fallbackPool = $fallbackPool;
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        $arrayAdapter = new ArrayAdapter();

        spl_autoload_register([ClassExistenceResource::class, 'throwOnRequiredClass']);
        try {
            if (!$this->doWarmUp($cacheDir, $arrayAdapter)) {
                return;
            }
        } finally {
            spl_autoload_unregister([ClassExistenceResource::class, 'throwOnRequiredClass']);
        }

        // the ArrayAdapter stores the values serialized
        // to avoid mutation of the data after it was written to the cache
        // so here we un-serialize the values first
        $values = array_map(function ($val) { return null !== $val ? unserialize($val) : null; }, $arrayAdapter->getValues());

        $this->warmUpPhpArrayAdapter(new PhpArrayAdapter($this->phpArrayFile, $this->fallbackPool), $values);

        foreach ($values as $k => $v) {
            $item = $this->fallbackPool->getItem($k);
            $this->fallbackPool->saveDeferred($item->set($v));
        }
        $this->fallbackPool->commit();
    }

    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values)
    {
        $phpArrayAdapter->warmUp($values);
    }

    /**
     * @internal
     */
    final protected function ignoreAutoloadException($class, \Exception $exception)
    {
        try {
            ClassExistenceResource::throwOnRequiredClass($class, $exception);
        } catch (\ReflectionException $e) {
        }
    }

    /**
     * @param string $cacheDir
     *
     * @return bool false if there is nothing to warm-up
     */
    abstract protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter);
}
