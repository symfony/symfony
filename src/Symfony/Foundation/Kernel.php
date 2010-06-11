<?php

namespace Symfony\Foundation;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Dumper\PhpDumper;
use Symfony\Components\DependencyInjection\FileResource;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\HttpKernelInterface;

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
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.org>
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
    protected $request;

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
        $this->request = null;
    }

    abstract public function registerRootDir();

    abstract public function registerBundles();

    abstract public function registerBundleDirs();

    abstract public function registerContainerConfiguration();

    abstract public function registerRoutes();

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
     * @return Kernel The current Kernel instance
     *
     * @throws \LogicException When the Kernel is already booted
     */
    public function boot()
    {
        if (true === $this->booted) {
            throw new \LogicException('The kernel is already booted.');
        }

        require_once __DIR__.'/bootstrap.php';

        $this->bundles = $this->registerBundles();
        $this->bundleDirs = $this->registerBundleDirs();

        // initialize the container
        $this->container = $this->initializeContainer();
        $this->container->setService('kernel', $this);

        // boot bundles
        foreach ($this->bundles as $bundle) {
            $bundle->boot($this->container);
        }

        $this->booted = true;

        return $this;
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
            $bundle->shutdown($this->container);
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
     * Gets the Request instance associated with the master request.
     *
     * @return Request A Request instance
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Handles a request to convert it to a response by calling the HttpKernel service.
     *
     * @param  Request $request A Request instance
     * @param  integer $type    The type of the request (one of HttpKernelInterface::MASTER_REQUEST, HttpKernelInterface::FORWARDED_REQUEST, or HttpKernelInterface::EMBEDDED_REQUEST)
     * @param  Boolean $raw     Whether to catch exceptions or not
     *
     * @return Response $response A Response instance
     */
    public function handle(Request $request = null, $type = HttpKernelInterface::MASTER_REQUEST, $raw = false)
    {
        if (false === $this->booted) {
            $this->boot();
        }

        if (null === $request) {
            $request = $this->container->getRequestService();
        }

        if (HttpKernelInterface::MASTER_REQUEST === $type) {
            $this->request = $request;
        }

        $this->container->setService('request', $request);

        $response = $this->container->getHttpKernelService()->handle($request, $type, $raw);

        $this->container->setService('request', $this->request);

        return $response;
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

        return new $class();
    }

    public function getKernelParameters()
    {
        $bundles = array();
        foreach ($this->bundles as $bundle) {
            $bundles[] = get_class($bundle);
        }

        return array_merge(
            array(
                'kernel.root_dir'    => $this->rootDir,
                'kernel.environment' => $this->environment,
                'kernel.debug'       => $this->debug,
                'kernel.name'        => $this->name,
                'kernel.cache_dir'   => $this->getCacheDir(),
                'kernel.logs_dir'    => $this->getLogDir(),
                'kernel.bundle_dirs' => $this->bundleDirs,
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

    protected function buildContainer($class, $file)
    {
        $container = new Builder($this->getKernelParameters());

        $configuration = new BuilderConfiguration();
        foreach ($this->bundles as $bundle) {
            $configuration->merge($bundle->buildContainer($container));
        }
        $configuration->merge($this->registerContainerConfiguration());
        $container->merge($configuration);
        $this->optimizeContainer($container);

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
            // add the Kernel class hierarchy as resources
            $parent = new \ReflectionObject($this);
            $configuration->addResource(new FileResource($parent->getFileName()));
            while ($parent = $parent->getParentClass()) {
                $configuration->addResource(new FileResource($parent->getFileName()));
            }

            // save the resources
            $this->writeCacheFile($this->getCacheDir().'/'.$class.'.meta', serialize($configuration->getResources()));
        }
    }

    public function optimizeContainer(Builder $container)
    {
        // replace all classes with the real value
        foreach ($container->getDefinitions() as $definition) {
            if (false !== strpos($class = $definition->getClass(), '%')) {
                $definition->setClass(Builder::resolveValue($class, $container->getParameters()));
            }
        }
    }

    static public function stripComments($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $ignore = array(T_COMMENT => true, T_DOC_COMMENT => true);
        $output = '';
        foreach (token_get_all($source) as $token) {
            // array
            if (isset($token[1])) {
                // no action on comments
                if (!isset($ignore[$token[0]])) {
                    // anything else -> output "as is"
                    $output .= $token[1];
                }
            } else {
                // simple 1-character token
                $output .= $token;
            }
        }

        return $output;
    }

    protected function writeCacheFile($file, $content)
    {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (!$fp = @fopen($tmpFile, 'wb')) {
            die(sprintf('Failed to write cache file "%s".', $tmpFile));
        }
        @fwrite($fp, $content);
        @fclose($fp);

        if ($content != file_get_contents($tmpFile)) {
            die(sprintf('Failed to write cache file "%s" (cache corrupted).', $tmpFile));
        }

        @rename($tmpFile, $file);
        chmod($file, 0644);
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
