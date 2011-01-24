<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\ClassCollectionLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The Kernel is the heart of the Symfony system.
 *
 * It manages an environment made of bundles.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.org>
 */
abstract class Kernel implements HttpKernelInterface, \Serializable
{
    protected $bundles;
    protected $bundleMap;
    protected $container;
    protected $rootDir;
    protected $environment;
    protected $debug;
    protected $booted;
    protected $name;
    protected $startTime;

    const VERSION = '2.0.0-DEV';

    /**
     * Constructor.
     *
     * @param string  $environment The environment
     * @param Boolean $debug       Whether to enable debugging or not
     */
    public function __construct($environment, $debug)
    {
        $this->environment = $environment;
        $this->debug = (Boolean) $debug;
        $this->booted = false;
        $this->rootDir = realpath($this->registerRootDir());
        $this->name = basename($this->rootDir);

        if ($this->debug) {
            ini_set('display_errors', 1);
            error_reporting(-1);

            $this->startTime = microtime(true);
        } else {
            ini_set('display_errors', 0);
        }
    }

    public function __clone()
    {
        if ($this->debug) {
            $this->startTime = microtime(true);
        }

        $this->booted = false;
        $this->container = null;
    }

    /**
     * Returns the root directory of this application.
     *
     * Most of the time, this is just __DIR__.
     *
     * @return string A directory path
     */
    abstract public function registerRootDir();

    /**
     * Returns an array of bundles to registers.
     *
     * @return array An array of bundle instances.
     */
    abstract public function registerBundles();

    /**
     * Loads the container configuration
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    abstract public function registerContainerConfiguration(LoaderInterface $loader);

    /**
     * Boots the current kernel.
     *
     * This method boots the bundles, which MUST set
     * the DI container.
     *
     * @throws \LogicException When the Kernel is already booted
     */
    public function boot()
    {
        if (true === $this->booted) {
            return;
        }

        require_once __DIR__.'/bootstrap.php';

        // init bundles
        $this->initializeBundles();

        // init container
        $this->initializeContainer();

        // load core classes
        ClassCollectionLoader::load(
            $this->container->getParameter('kernel.compiled_classes'),
            $this->container->getParameter('kernel.cache_dir'),
            'classes',
            $this->container->getParameter('kernel.debug'),
            true
        );

        foreach ($this->bundles as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->booted = true;
    }

    /**
     * Shutdowns the kernel.
     *
     * This method is mainly useful when doing functional testing.
     */
    public function shutdown()
    {
        $this->booted = false;

        foreach ($this->bundles as $bundle) {
            $bundle->shutdown();
            $bundle->setContainer(null);
        }

        $this->container = null;
    }

    /**
     * Reboots the kernel.
     *
     * This method is mainly useful when doing functional testing.
     *
     * It is a shortcut for the call to shutdown() and boot().
     */
    public function reboot()
    {
        $this->shutdown();
        $this->boot();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if (false === $this->booted) {
            $this->boot();
        }

        return $this->container->get('http_kernel')->handle($request, $type, $catch);
    }

    /**
     * Gets the registered bundle instances.
     *
     * @return array An array of registered bundle instances
     */
    public function getBundles()
    {
        return $this->bundles;
    }

    /**
     * Checks if a given class name belongs to an active bundle.
     *
     * @param string $class A class name
     *
     * @return Boolean true if the class belongs to an active bundle, false otherwise
     */
    public function isClassInActiveBundle($class)
    {
        foreach ($this->bundles as $bundle) {
            $bundleClass = get_class($bundle);
            if (0 === strpos($class, substr($bundleClass, 0, strrpos($bundleClass, '\\')))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a bundle by its name.
     *
     * @param string  $name  Bundle name
     * @param Boolean $first Whether to return the first bundle or all bundles matching this name
     *
     * @return BundleInterface A BundleInterface instance
     *
     * @throws \InvalidArgumentException when the bundle is not enabled
     */
    public function getBundle($name, $first = true)
    {
        if (!isset($this->bundleMap[$name])) {
            throw new \InvalidArgumentException(sprintf('Bundle "%s" does not exist or it is not enabled.', $name));
        }

        if (true === $first) {
            return $this->bundleMap[$name][0];
        } elseif (false === $first) {
            return $this->bundleMap[$name];
        }
    }

    /**
     * Returns the file path for a given resource.
     *
     * A Resource can be a file or a directory.
     *
     * The resource name must follow the following pattern:
     *
     *     @BundleName/path/to/a/file.something
     *
     * where BundleName is the name of the bundle
     * and the remaining part is the relative path in the bundle.
     *
     * If $dir is passed, and the first segment of the path is Resources,
     * this method will look for a file named:
     *
     *     $dir/BundleName/path/without/Resources
     *
     * @param string  $name  A resource name to locate
     * @param string  $dir   A directory where to look for the resource first
     * @param Boolean $first Whether to return the first path or paths for all matching bundles
     *
     * @return string|array The absolute path of the resource or an array if $first is false
     *
     * @throws \InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws \RuntimeException         if the name contains invalid/unsafe characters
     */
    public function locateResource($name, $dir = null, $first = true)
    {
        if ('@' !== $name[0]) {
            throw new \InvalidArgumentException(sprintf('A resource name must start with @ ("%s" given).', $name));
        }

        if (false !== strpos($name, '..')) {
            throw new \RuntimeException(sprintf('File name "%s" contains invalid characters (..).', $name));
        }

        $name = substr($name, 1);
        list($bundle, $path) = explode('/', $name, 2);

        $isResource = 0 === strpos($path, 'Resources');

        $files = array();
        if (true === $isResource && null !== $dir && file_exists($file = $dir.'/'.$bundle.'/'.substr($path, 10))) {
            if ($first) {
                return $file;
            }

            $files[] = $file;
        }

        foreach ($this->getBundle($bundle, false) as $bundle) {
            if (file_exists($file = $bundle->getPath().'/'.$path)) {
                if ($first) {
                    return $file;
                }
                $files[] = $file;
            }
        }

        if ($files) {
            return $files;
        }

        throw new \InvalidArgumentException(sprintf('Unable to find file "@%s".', $name));
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSafeName()
    {
        return preg_replace('/[^a-zA-Z0-9_]+/', '', $this->name);
    }

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Checks if debug mode is enabled.
     *
     * @return Boolean true if debug mode is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * Gets the application root dir.
     *
     * @return string The application root dir
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * Gets the current container.
     *
     * @return ContainerInterface A ContainerInterface instance
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Gets the request start time (not available if debug is disabled).
     *
     * @return integer The request start timestamp
     */
    public function getStartTime()
    {
        return $this->debug ? $this->startTime : -INF;
    }

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     */
    public function getCacheDir()
    {
        return $this->rootDir.'/cache/'.$this->environment;
    }

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     */
    public function getLogDir()
    {
        return $this->rootDir.'/logs';
    }

    protected function initializeBundles()
    {
        // init bundles
        $this->bundles = array();
        $this->bundleMap = array();
        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            $this->bundles[$name] = $bundle;
            if (!isset($this->bundleMap[$name])) {
                $this->bundleMap[$name] = array();
            }
            $this->bundleMap[$name][] = $bundle;
        }

        // inheritance
        $extended = array();
        foreach ($this->bundles as $name => $bundle) {
            $parent = $bundle;
            $first = true;
            while ($parentName = $parent->getParent()) {
                if (!isset($this->bundles[$parentName])) {
                    throw new \LogicException(sprintf('Bundle "%s" extends bundle "%s", which is not registered.', $name, $parentName));
                }

                if ($first && isset($extended[$parentName])) {
                    throw new \LogicException(sprintf('Bundle "%s" is directly extended by two bundles "%s" and "%s".', $parentName, $name, $extended[$parentName]));
                }

                $first = false;
                $parent = $this->bundles[$parentName];
                $extended[$parentName] = $name;
                array_unshift($this->bundleMap[$parentName], $bundle);
            }
        }
    }

    protected function initializeContainer()
    {
        $class = $this->getSafeName().ucfirst($this->environment).($this->debug ? 'Debug' : '').'ProjectContainer';
        $location = $this->getCacheDir().'/'.$class;
        $reload = $this->debug ? $this->needsReload($class, $location) : false;

        $fresh = false;
        if ($reload || !file_exists($location.'.php')) {
            $container = $this->buildContainer();
            $this->dumpContainer($container, $class, $location.'.php');

            $fresh = true;
        }

        require_once $location.'.php';

        $this->container = new $class();
        $this->container->set('kernel', $this);

        if ($fresh && 'cli' !== php_sapi_name()) {
            $this->container->get('cache_warmer')->warmUp($this->container->getParameter('kernel.cache_dir'));
        }
    }

    public function getKernelParameters()
    {
        $bundles = array();
        foreach ($this->bundles as $name => $bundle) {
            $bundles[$name] = get_class($bundle);
        }

        return array_merge(
            array(
                'kernel.root_dir'    => $this->rootDir,
                'kernel.environment' => $this->environment,
                'kernel.debug'       => $this->debug,
                'kernel.name'        => $this->name,
                'kernel.cache_dir'   => $this->getCacheDir(),
                'kernel.logs_dir'    => $this->getLogDir(),
                'kernel.bundles'     => $bundles,
                'kernel.charset'     => 'UTF-8',
            ),
            $this->getEnvParameters()
        );
    }

    protected function getEnvParameters()
    {
        $parameters = array();
        foreach ($_SERVER as $key => $value) {
            if ('SYMFONY__' === substr($key, 0, 9)) {
                $parameters[strtolower(str_replace('__', '.', substr($key, 9)))] = $value;
            }
        }

        return $parameters;
    }

    protected function needsReload($class, $location)
    {
        if (!file_exists($location.'.meta') || !file_exists($location.'.php')) {
            return true;
        }

        $meta = unserialize(file_get_contents($location.'.meta'));
        $time = filemtime($location.'.php');
        foreach ($meta as $resource) {
            if (!$resource->isUptodate($time)) {
                return true;
            }
        }

        return false;
    }

    protected function buildContainer()
    {
        $parameterBag = new ParameterBag($this->getKernelParameters());

        $container = new ContainerBuilder($parameterBag);
        foreach ($this->bundles as $bundle) {
            $bundle->registerExtensions($container);

            if ($this->debug) {
                $container->addObjectResource($bundle);
            }
        }

        if (null !== $cont = $this->registerContainerConfiguration($this->getContainerLoader($container))) {
            $container->merge($cont);
        }
        $container->compile();

        return $container;
    }

    protected function dumpContainer(ContainerBuilder $container, $class, $file)
    {
        foreach (array('cache', 'logs') as $name) {
            $dir = $container->getParameter(sprintf('kernel.%s_dir', $name));
            if (!is_dir($dir)) {
                if (false === @mkdir($dir, 0777, true)) {
                    die(sprintf('Unable to create the %s directory (%s)', $name, dirname($dir)));
                }
            } elseif (!is_writable($dir)) {
                die(sprintf('Unable to write in the %s directory (%s)', $name, $dir));
            }
        }

        // cache the container
        $dumper = new PhpDumper($container);
        $content = $dumper->dump(array('class' => $class));
        if (!$this->debug) {
            $content = self::stripComments($content);
        }
        $this->writeCacheFile($file, $content);

        if ($this->debug) {
            $container->addObjectResource($this);

            // save the resources
            $this->writeCacheFile($this->getCacheDir().'/'.$class.'.meta', serialize($container->getResources()));
        }
    }

    protected function getContainerLoader(ContainerInterface $container)
    {
        $resolver = new LoaderResolver(array(
            new XmlFileLoader($container),
            new YamlFileLoader($container),
            new IniFileLoader($container),
            new PhpFileLoader($container),
            new ClosureLoader($container),
        ));

        return new DelegatingLoader($resolver);
    }

    /**
     * Removes comments from a PHP source string.
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the comments removed
     */
    static public function stripComments($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (!in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= $token[1];
            }
        }

        // replace multiple new lines with a single newline
        $output = preg_replace(array('/\s+$/Sm', '/\n+/S'), "\n", $output);

        return $output;
    }

    protected function writeCacheFile($file, $content)
    {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            chmod($file, 0644);

            return;
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }

    public function serialize()
    {
        return serialize(array($this->environment, $this->debug));
    }

    public function unserialize($data)
    {
        list($environment, $debug) = unserialize($data);

        $this->__construct($environment, $debug);
    }
}
