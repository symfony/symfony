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

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

abstract class AbstractPhpFileCacheWarmer implements CacheWarmerInterface
{
    private string $phpArrayFile;

    /**
     * @param string $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(string $phpArrayFile)
    {
        $this->phpArrayFile = $phpArrayFile;
    }

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    public function warmUp(string $cacheDir): array
    {
        $arrayAdapter = new ArrayAdapter();

        spl_autoload_register([ClassExistenceResource::class, 'throwOnRequiredClass']);
        try {
            if (!$this->doWarmUp($cacheDir, $arrayAdapter)) {
                return [];
            }
        } finally {
            spl_autoload_unregister([ClassExistenceResource::class, 'throwOnRequiredClass']);
        }

        // the ArrayAdapter stores the values serialized
        // to avoid mutation of the data after it was written to the cache
        // so here we un-serialize the values first
        $values = array_map(function ($val) { return null !== $val ? unserialize($val) : null; }, $arrayAdapter->getValues());

        return $this->warmUpPhpArrayAdapter(new PhpArrayAdapter($this->phpArrayFile, new NullAdapter()), $values);
    }

    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values): array
    {
        return (array) $phpArrayAdapter->warmUp($values);
    }

    /**
     * @internal
     */
    final protected function ignoreAutoloadException(string $class, \Exception $exception): void
    {
        try {
            ClassExistenceResource::throwOnRequiredClass($class, $exception);
        } catch (\ReflectionException) {
        }
    }

    /**
     * @return bool false if there is nothing to warm-up
     */
    abstract protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter): bool;
}
