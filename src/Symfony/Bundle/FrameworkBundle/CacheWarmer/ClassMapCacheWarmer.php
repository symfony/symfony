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

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates an autoload class map cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassMapCacheWarmer extends CacheWarmer
{
    protected $container;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel  A KernelInterface instance
     * @param string          $rootDir The directory where global templates can be stored
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        if (!$this->container->hasParameter('kernel.autoload_classes')) {
            return;
        }

        $classes = array();
        foreach ($this->container->getParameter('kernel.autoload_classes') as $class) {
            $r = new \ReflectionClass($class);

            $classes[$class] = $r->getFilename();
        }

        $this->writeCacheFile($cacheDir.'/autoload.php', sprintf('<?php return %s;', var_export($classes, true)));
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return Boolean always false
     */
    public function isOptional()
    {
        return true;
    }
}
