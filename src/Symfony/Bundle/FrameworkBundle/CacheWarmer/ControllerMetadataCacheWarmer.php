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

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataUtil;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates the cache for controller metadata.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ControllerMetadataCacheWarmer implements CacheWarmerInterface
{
    private $router;
    private $controllerResolver;
    private $controllerMetadataFactory;
    private $configCacheFactory;
    private $cacheFile;

    public function __construct(RouterInterface $router, ControllerResolverInterface $controllerResolver, ConfigCacheFactoryInterface $configCacheFactory, ControllerMetadataFactoryInterface $controllerMetadataFactory, $cacheFile)
    {
        $this->router = $router;
        $this->controllerResolver = $controllerResolver;
        $this->configCacheFactory = $configCacheFactory;
        $this->controllerMetadataFactory = $controllerMetadataFactory;
        $this->cacheFile = $cacheFile;
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
     * {@inheritdoc}
     */
    public function isOptional()
    {
        return true;
    }

    private function refreshCache(ConfigCacheInterface $cache)
    {
        // make sure the cache is not used when executing createControllerMetadata
        (new Filesystem())->remove($cache->getPath());

        $controllers = array();

        foreach ($this->router->getRouteCollection()->all() as $route) {
            /* @var $route Route */
            if (!$route->hasDefault('_controller')) {
                continue;
            }

            $controller = $this->controllerResolver->getController(new Request(array(), array(), array('_controller' => $route->getDefault('_controller'))));

            if (null === ($logicalName = ControllerMetadataUtil::getControllerLogicalName($controller))) {
                continue;
            }

            list($className, $methodName) = $logicalName;

            if (null === $className || isset($controllers[$className])) {
                // either a closure or controller is already processed
                continue;
            }

            $controllers[$className][$methodName] = true;

            // Not all actions are mapped by routes, such as sub-requests. Just add everything public as possibility
            $reflection = new \ReflectionClass($className);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // avoid magic methods
                if (0 === strpos($method->getName(), '__')) {
                    continue;
                }

                $controllers[$className][$method->getName()] = true;
            }
        }

        $metadatas = array();
        $trackedFiles = array();

        foreach ($controllers as $controller => $methods) {
            foreach ($methods as $method => $unused) {
                $metadata = $this->controllerMetadataFactory->createControllerMetadata(array($controller, $method));
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
        $cache->write($content, $trackedFiles);
    }
}
