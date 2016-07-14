<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata;

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Controller\ControllerFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataUtil;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class CachedControllerMetadataFactory implements ControllerMetadataFactoryInterface, WarmableInterface
{
    private $controllerFactory;
    private $configCacheFactory;
    private $baseFactory;
    private $router;

    /**
     * Contains all previously requested metadata.
     *
     * @var ControllerMetadata[]
     */
    private $requested = array();

    private $cacheFile;
    /**
     * Contains serialized controller metedata for each pre-defined controller.
     *
     * @var string[]
     */
    private $warmed = array();

    public function __construct(ControllerFactoryInterface $controllerFactory, ControllerMetadataFactoryInterface $baseFactory, RouterInterface $router, ConfigCacheFactoryInterface $configCacheFactory, $cacheFile)
    {
        $this->controllerFactory = $controllerFactory;
        $this->baseFactory = $baseFactory;
        $this->router = $router;
        $this->configCacheFactory = $configCacheFactory;
        $this->cacheFile = $cacheFile;
    }

    /**
     * {@inheritdoc}
     */
    public function createControllerMetadata(callable $controller)
    {
        if (null === ($logicalName = ControllerMetadataUtil::getControllerLogicalName($controller))) {
            return;
        }

        $cache = $this->configCacheFactory->cache($this->cacheFile, function (ConfigCacheInterface $cache) {
            $this->refreshCache($cache);
        });

        if (empty($this->warmed)) {
            $this->warmed = include $cache->getPath();
        }

        $index = implode(':', $logicalName);

        // if already requested
        if (isset($this->requested[$index])) {
            return $this->requested[$index];
        }

        // if not requested but in cache
        if (isset($this->warmed[$index])) {
            return $this->requested[$index] = unserialize($this->warmed[$index]);
        }

        // not in cache but lets cache it for at least this request
        return $this->requested[$index] = $this->baseFactory->createControllerMetadata($controller);
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        $this->configCacheFactory->cache($this->cacheFile, function (ConfigCacheInterface $cache) {
            $this->refreshCache($cache);
        });
    }

    /**
     * Builds the actual cache.
     *
     * @param ConfigCacheInterface $cache
     */
    private function refreshCache(ConfigCacheInterface $cache)
    {
        $controllers = array();

        foreach ($this->router->getRouteCollection()->all() as $route) {
            if (!$route->hasDefault('_controller')) {
                continue;
            }

            $controller = $this->controllerFactory->createFromString($route->getDefault('_controller'));

            if (null === ($logicalName = ControllerMetadataUtil::getControllerLogicalName($controller))) {
                continue;
            }

            list($className) = $logicalName;

            if (null === $className || isset($controllers[$className])) {
                // either a closure or controller is already processed
                continue;
            }

            // Not all actions are mapped by routes, such as sub-requests. Just add everything public as possibility
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // avoid magic methods
                if ($method->isConstructor() || $method->isDestructor()) {
                    continue;
                }

                $controllers[$className][] = $method->getName();
            }
        }

        $metadatas = array();
        $trackedFiles = array();

        foreach ($controllers as $controller => $methods) {
            foreach ($methods as $method) {
                $metadata = $this->baseFactory->createControllerMetadata(array($controller, $method));
                $metadatas[$controller.':'.$method] = serialize($metadata);
                foreach ($metadata->getTrackedFiles() as $file) {
                    if (isset($trackedFiles[$file])) {
                        continue;
                    }

                    $trackedFiles[$file] = new FileResource($file);
                }
            }
        }

        $content = sprintf(<<<EOF
<?php

return %s;

EOF
            ,
            var_export($metadatas, true)
        );

        $cache->write($content, array_values($trackedFiles));

        $this->warmed = $metadatas;
    }
}
