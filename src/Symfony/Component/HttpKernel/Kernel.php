<?php

namespace Symfony\Component\HttpKernel;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Resource\FileResource;
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

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * The Kernel is the heart of the Symfony system. It manages an environment
 * that can host bundles.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.org>
 */
abstract class Kernel implements HttpKernelInterface, \Serializable
{
    protected $bundles;
    protected $bundleDirs;
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

    abstract public function registerRootDir();

    abstract public function registerBundles();

    abstract public function registerBundleDirs();

    abstract public function registerContainerConfiguration(LoaderInterface $loader);

    /**
     * Checks whether the current kernel has been booted or not.
     *
     * @return boolean $booted
     */
    public function isBooted()
    {
        return $this->booted;
    }

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
            throw new \LogicException('The kernel is already booted.');
        }

        if (!$this->isDebug()) {
            require_once __DIR__.'/bootstrap.php';
        }

        $this->bundles = $this->registerBundles();
        $this->bundleDirs = $this->registerBundleDirs();
        $this->container = $this->initializeContainer();

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
     * {@inheritdoc}
     */
    public function getRequest()
    {
        return $this->container->get('http_kernel')->getRequest();
    }

    /**
     * Gets the directories where bundles can be stored.
     *
     * @return array An array of directories where bundles can be stored
     */
    public function getBundleDirs()
    {
        return $this->bundleDirs;
    }

    /**
     * Gets the registered bundle names.
     *
     * @return array An array of registered bundle names
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
     * Returns the Bundle name for a given class.
     *
     * @param string $class A class name
     *
     * @return string The Bundle name or null if the class does not belongs to a bundle
     */
    public function getBundleForClass($class)
    {
        $namespace = substr($class, 0, strrpos($class, '\\'));
        foreach (array_keys($this->getBundleDirs()) as $prefix) {
            if (0 === $pos = strpos($namespace, $prefix)) {
                return substr($namespace, strlen($prefix) + 1, strpos($class, 'Bundle\\') + 7);
            }
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSafeName()
    {
        return preg_replace('/[^a-zA-Z0-9_]+/', '', $this->name);
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function isDebug()
    {
        return $this->debug;
    }

    public function getRootDir()
    {
        return $this->rootDir;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getStartTime()
    {
        return $this->debug ? $this->startTime : -INF;
    }

    public function getCacheDir()
    {
        return $this->rootDir.'/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return $this->rootDir.'/logs';
    }

    protected function initializeContainer()
    {
        $class = $this->getSafeName().ucfirst($this->environment).($this->debug ? 'Debug' : '').'ProjectContainer';
        $location = $this->getCacheDir().'/'.$class;
        $reload = $this->debug ? $this->needsReload($class, $location) : false;

        if ($reload || !file_exists($location.'.php')) {
            $this->buildContainer($class, $location.'.php');
        }

        require_once $location.'.php';

        $container = new $class();
        $container->set('kernel', $this);

        return $container;
    }

    public function getKernelParameters()
    {
        $bundles = array();
        foreach ($this->bundles as $bundle) {
            $bundles[] = get_class($bundle);
        }

        return array_merge(
            array(
                'kernel.root_dir'         => $this->rootDir,
                'kernel.environment'      => $this->environment,
                'kernel.debug'            => $this->debug,
                'kernel.name'             => $this->name,
                'kernel.cache_dir'        => $this->getCacheDir(),
                'kernel.logs_dir'         => $this->getLogDir(),
                'kernel.bundle_dirs'      => $this->bundleDirs,
                'kernel.bundles'          => $bundles,
                'kernel.charset'          => 'UTF-8',
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

    protected function buildContainer($class, $file)
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
        $container->freeze();

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
            new XmlFileLoader($container, $this->getBundleDirs()),
            new YamlFileLoader($container, $this->getBundleDirs()),
            new IniFileLoader($container, $this->getBundleDirs()),
            new PhpFileLoader($container, $this->getBundleDirs()),
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

        // reformat {} "a la python"
        $output = preg_replace(array('/\n\s*\{/', '/\n\s*\}/'), array(' {', ' }'), $output);

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
