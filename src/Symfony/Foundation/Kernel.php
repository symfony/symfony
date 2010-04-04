<?php

namespace Symfony\Foundation;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Dumper\PhpDumper;
use Symfony\Components\DependencyInjection\FileResource;
use Symfony\Components\RequestHandler\RequestInterface;

/**
 * The Kernel is the heart of the Symfony system. It manages an environment
 * that can host bundles.
 *
 * @package Symfony
 * @author  Fabien Potencier <fabien.potencier@symfony-project.org>
 */
abstract class Kernel implements \Serializable
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
    $this->debug = (Boolean) $debug;
    if ($this->debug)
    {
      ini_set('display_errors', 1);
      error_reporting(-1);
    }
    else
    {
      ini_set('display_errors', 0);
    }

    if ($this->debug)
    {
      $this->startTime = microtime(true);
    }

    $this->booted = false;
    $this->environment = $environment;
    $this->bundles = $this->registerBundles();
    $this->bundleDirs = $this->registerBundleDirs();
    $this->rootDir = realpath($this->registerRootDir());
    $this->name = basename($this->rootDir);
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
    if (true === $this->booted)
    {
      throw new \LogicException('The kernel is already booted.');
    }

    // initialize the container
    $this->container = $this->initializeContainer();
    $this->container->setService('kernel', $this);

    // boot bundles (in reverse order)
    foreach ($this->bundles as $bundle)
    {
      $bundle->boot($this->container);
    }

    $this->booted = true;

    return $this;
  }

  public function run()
  {
    $this->handle()->send();
  }

  public function handle(RequestInterface $request = null)
  {
    if (false === $this->booted)
    {
      $this->boot();
    }

    if (null === $request)
    {
      $request = $this->container->getRequestService();
    }

    return $this->container->getRequestHandlerService()->handle($request);
  }

  public function getBundleDirs()
  {
    return $this->bundleDirs;
  }

  public function getBundles()
  {
    return $this->bundles;
  }

  public function getName()
  {
    return $this->name;
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
    $class = $this->name.'ProjectContainer';
    $location = $this->getCacheDir().'/'.$class;
    $reload = $this->debug ? $this->needsReload($class, $location) : false;

    if ($reload || !file_exists($location.'.php'))
    {
      $this->buildContainer($class, $location.'.php');
    }

    require_once $location.'.php';

    return new $class();
  }

  public function getKernelParameters()
  {
    $bundles = array();
    foreach ($this->bundles as $bundle)
    {
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
    foreach ($_SERVER as $key => $value)
    {
      if ('SYMFONY__' === substr($key, 0, 9))
      {
        $parameters[strtolower(str_replace('__', '.', substr($key, 9)))] = $value;
      }
    }

    return $parameters;
  }

  protected function needsReload($class, $location)
  {
    if (!file_exists($location.'.meta') || !file_exists($location.'.php'))
    {
      return true;
    }

    $meta = unserialize(file_get_contents($location.'.meta'));
    $time = filemtime($location.'.php');
    foreach ($meta as $resource)
    {
      if (!$resource->isUptodate($time))
      {
        return true;
      }
    }

    return false;
  }

  protected function buildContainer($class, $file)
  {
    $container = new Builder($this->getKernelParameters());

    $configuration = new BuilderConfiguration();
    foreach ($this->bundles as $bundle)
    {
      $configuration->merge($bundle->buildContainer($container));
    }
    $configuration->merge($this->registerContainerConfiguration());
    $container->merge($configuration);
    $this->optimizeContainer($container);

    foreach (array('cache', 'logs') as $name)
    {
      $dir = $container->getParameter(sprintf('kernel.%s_dir', $name));
      if (!is_dir($dir))
      {
        if (false === @mkdir($dir, 0777, true))
        {
          die(sprintf('Unable to create the %s directory (%s)', $name, dirname($dir)));
        }
      }
      elseif (!is_writable($dir))
      {
        die(sprintf('Unable to write in the %s directory (%s)', $name, $dir));
      }
    }

    // cache the container
    $dumper = new PhpDumper($container);
    $content = $dumper->dump(array('class' => $class));
    if (!$this->debug)
    {
      $content = self::stripComments($content);
    }
    $this->writeCacheFile($file, $content);

    if ($this->debug)
    {
      // add the Kernel class hierachy as resources
      $parent = new \ReflectionObject($this);
      $configuration->addResource(new FileResource($parent->getFileName()));
      while ($parent = $parent->getParentClass())
      {
        $configuration->addResource(new FileResource($parent->getFileName()));
      }

      // save the resources
      $this->writeCacheFile($this->getCacheDir().'/'.$class.'.meta', serialize($configuration->getResources()));
    }
  }

  public function optimizeContainer(Builder $container)
  {
    // replace all classes with the real value
    foreach ($container->getDefinitions() as $definition)
    {
      if (false !== strpos($class = $definition->getClass(), '%'))
      {
        $definition->setClass(Builder::resolveValue($class, $container->getParameters()));
        unset($container[substr($class, 1, -1)]);
      }
    }
  }

  static public function stripComments($source)
  {
    if (!function_exists('token_get_all'))
    {
      return $source;
    }

    $ignore = array(T_COMMENT => true, T_DOC_COMMENT => true);
    $output = '';
    foreach (token_get_all($source) as $token)
    {
      // array
      if (isset($token[1]))
      {
        // no action on comments
        if (!isset($ignore[$token[0]]))
        {
          // anything else -> output "as is"
          $output .= $token[1];
        }
      }
      else
      {
        // simple 1-character token
        $output .= $token;
      }
    }

    return $output;
  }

  protected function writeCacheFile($file, $content)
  {
    $tmpFile = tempnam(dirname($file), basename($file));
    if (!$fp = @fopen($tmpFile, 'wb'))
    {
      die(sprintf('Failed to write cache file "%s".', $tmpFile));
    }
    @fwrite($fp, $content);
    @fclose($fp);

    if ($content != file_get_contents($tmpFile))
    {
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
