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
use Symfony\Component\VarExporter\DeepCloner;

abstract class AbstractPhpFileCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param string $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(
        private string $phpArrayFile,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $arrayAdapter = new ArrayAdapter();

        spl_autoload_register([ClassExistenceResource::class, 'throwOnRequiredClass']);
        try {
            if (!$this->doWarmUp($cacheDir, $arrayAdapter, $buildDir)) {
                return [];
            }
        } finally {
            spl_autoload_unregister([ClassExistenceResource::class, 'throwOnRequiredClass']);
        }

        // the ArrayAdapter stores deep clones of the values to avoid mutation of
        // the cached data afterwards; older versions store them serialized
        // instead, so unserialize those (a serialized payload holds a colon,
        // which the adapter never leaves in a raw string) for backward compatibility
        $values = $arrayAdapter->getValues(true);
        foreach ($values as $key => $value) {
            if (null === $value) {
                unset($values[$key]);
            } elseif ($value instanceof DeepCloner) {
                $values[$key] = $value->clone(null, true);
            } elseif (\is_string($value) && str_contains($value, ':')) {
                $values[$key] = unserialize($value, ['allowed_classes' => true]);
            }
        }

        return $this->warmUpPhpArrayAdapter(new PhpArrayAdapter($this->phpArrayFile, new NullAdapter()), $values);
    }

    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values): array
    {
        return $phpArrayAdapter->warmUp($values);
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
    abstract protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool;
}
