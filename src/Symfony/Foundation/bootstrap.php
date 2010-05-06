<?php 

namespace Symfony\Foundation\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\Console\Application;




abstract class Bundle implements BundleInterface
{
    public function buildContainer(ContainerInterface $container)
    {
    }

    public function boot(ContainerInterface $container)
    {
    }

    public function shutdown(ContainerInterface $container)
    {
    }

    public function registerCommands(Application $application)
    {
        foreach ($application->getKernel()->getBundleDirs() as $dir)
        {
            $bundleBase = dirname(str_replace('\\', '/', get_class($this)));
            $commandDir = $dir.'/'.basename($bundleBase).'/Command';
            if (!is_dir($commandDir))
            {
                continue;
            }

                        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
            {
                if ($file->isDir() || substr($file, -4) !== '.php')
                {
                    continue;
                }

                $class = str_replace('/', '\\', $bundleBase).'\\Command\\'.str_replace(realpath($commandDir).'/', '', basename(realpath($file), '.php'));

                $r = new \ReflectionClass($class);

                if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract())
                {
                    $application->addCommand(new $class());
                }
            }
        }
    }
}


namespace Symfony\Foundation\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;




interface BundleInterface
{
    public function buildContainer(ContainerInterface $container);

    public function boot(ContainerInterface $container);

    public function shutdown(ContainerInterface $container);
}


namespace Symfony\Foundation\Bundle;

use Symfony\Foundation\Bundle\Bundle;
use Symfony\Foundation\ClassCollectionLoader;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;




class KernelBundle extends Bundle
{
    public function buildContainer(ContainerInterface $container)
    {
        Loader::registerExtension(new KernelExtension());

        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $configuration->merge($loader->load('services.xml'));

        if ($container->getParameter('kernel.debug'))
        {
            $configuration->merge($loader->load('debug.xml'));
            $configuration->setDefinition('event_dispatcher', $configuration->findDefinition('debug.event_dispatcher'));
        }

        return $configuration;
    }

    public function boot(ContainerInterface $container)
    {
        $container->getErrorHandlerService();

                if ($container->getParameter('kernel.include_core_classes'))
        {
            ClassCollectionLoader::load($container->getParameter('kernel.compiled_classes'), $container->getParameter('kernel.cache_dir'), 'classes', $container->getParameter('kernel.debug'));
        }
    }
}


namespace Symfony\Foundation\Bundle;

use Symfony\Components\DependencyInjection\Loader\LoaderExtension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\BuilderConfiguration;




class KernelExtension extends LoaderExtension
{
    public function testLoad($config)
    {
        $configuration = new BuilderConfiguration();

        $loader = new XmlFileLoader(array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $configuration->merge($loader->load('test.xml'));
        $configuration->setParameter('kernel.include_core_classes', false);

        return $configuration;
    }

    public function configLoad($config)
    {
        $configuration = new BuilderConfiguration();

        if (isset($config['charset']))
        {
            $configuration->setParameter('kernel.charset', $config['charset']);
        }

        if (!array_key_exists('compilation', $config))
        {
            $classes = array(
                'Symfony\\Components\\Routing\\Router',
                'Symfony\\Components\\Routing\\RouterInterface',
                'Symfony\\Components\\EventDispatcher\\Event',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcherInterface',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcher',
                'Symfony\\Components\\HttpKernel\\HttpKernel',
                'Symfony\\Components\\HttpKernel\\Request',
                'Symfony\\Components\\HttpKernel\\Response',
                'Symfony\\Components\\Templating\\Loader\\LoaderInterface',
                'Symfony\\Components\\Templating\\Loader\\Loader',
                'Symfony\\Components\\Templating\\Loader\\FilesystemLoader',
                'Symfony\\Components\\Templating\\Engine',
                'Symfony\\Components\\Templating\\Renderer\\RendererInterface',
                'Symfony\\Components\\Templating\\Renderer\\Renderer',
                'Symfony\\Components\\Templating\\Renderer\\PhpRenderer',
                'Symfony\\Components\\Templating\\Storage\\Storage',
                'Symfony\\Components\\Templating\\Storage\\FileStorage',
                'Symfony\\Framework\\WebBundle\\Controller',
                'Symfony\\Framework\\WebBundle\\Listener\\RequestParser',
                'Symfony\\Framework\\WebBundle\\Listener\\ControllerLoader',
                'Symfony\\Framework\\WebBundle\\Listener\\ResponseFilter',
                'Symfony\\Framework\\WebBundle\\Templating\\Engine',
            );
        }
        else
        {
            $classes = array();
            foreach (explode("\n", $config['compilation']) as $class)
            {
                if ($class)
                {
                    $classes[] = trim($class);
                }
            }
        }
        $configuration->setParameter('kernel.compiled_classes', $classes);

        if (array_key_exists('error_handler_level', $config))
        {
            $configuration->setParameter('error_handler.level', $config['error_handler_level']);
        }

        return $configuration;
    }

    
    public function getXsdValidationBasePath()
    {
        return false;
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/symfony/kernel';
    }

    public function getAlias()
    {
        return 'kernel';
    }
}


namespace Symfony\Foundation\Debug;




class ErrorHandler
{
    protected $levels = array(
        E_WARNING           => 'Warning',
        E_NOTICE            => 'Notice',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
    );

    protected $level;

    
    public function __construct($level = null)
    {
        $this->level = null === $level ? error_reporting() : $level;
    }

    public function register()
    {
        set_error_handler(array($this, 'handle'));
    }

    
    public function handle($level, $message, $file, $line, $context)
    {
        if (0 === $this->level)
        {
            return false;
        }

        if (error_reporting() & $level && $this->level & $level)
        {
            throw new \ErrorException(sprintf('%s: %s in %s line %d', isset($this->levels[$level]) ? $this->levels[$level] : $level, $message, $file, $line));
        }

        return false;
    }
}


namespace Symfony\Foundation;




class ClassCollectionLoader
{
    
    static public function load($classes, $cacheDir, $name, $autoReload)
    {
        $cache = $cacheDir.'/'.$name.'.php';

                $reload = false;
        if ($autoReload)
        {
            $metadata = $cacheDir.'/'.$name.'.meta';
            if (!file_exists($metadata) || !file_exists($cache))
            {
                $reload = true;
            }
            else
            {
                $time = filemtime($cache);
                $meta = unserialize(file_get_contents($metadata));

                if ($meta[1] != $classes)
                {
                    $reload = true;
                }
                else
                {
                    foreach ($meta[0] as $resource)
                    {
                        if (!file_exists($resource) || filemtime($resource) > $time)
                        {
                            $reload = true;

                            break;
                        }
                    }
                }
            }
        }

        if (!$reload && file_exists($cache))
        {
            require_once $cache;

            return;
        }

        $files = array();
        $content = '';
        foreach ($classes as $class)
        {
            if (!class_exists($class) && !interface_exists($class))
            {
                throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class));
            }

            $r = new \ReflectionClass($class);
            $files[] = $r->getFileName();

            $content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName()));
        }

                if (!is_dir(dirname($cache)))
        {
            mkdir(dirname($cache), 0777, true);
        }
        self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));

        if ($autoReload)
        {
                        self::writeCacheFile($metadata, serialize(array($files, $classes)));
        }
    }

    static protected function writeCacheFile($file, $content)
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
}


namespace Symfony\Foundation;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Dumper\PhpDumper;
use Symfony\Components\DependencyInjection\FileResource;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\HttpKernelInterface;




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

    
    public function __construct($environment, $debug)
    {
        $this->environment = $environment;
        $this->debug = (Boolean) $debug;
        $this->booted = false;
        $this->rootDir = realpath($this->registerRootDir());
        $this->name = basename($this->rootDir);

        if ($this->debug)
        {
            ini_set('display_errors', 1);
            error_reporting(-1);

            $this->startTime = microtime(true);
        }
        else
        {
            ini_set('display_errors', 0);
        }
    }

    abstract public function registerRootDir();

    abstract public function registerBundles();

    abstract public function registerBundleDirs();

    abstract public function registerContainerConfiguration();

    abstract public function registerRoutes();

    
    public function isBooted()
    {
        return $this->booted;
    }

    
    public function boot()
    {
        if (true === $this->booted)
        {
            throw new \LogicException('The kernel is already booted.');
        }

        $this->bundles = $this->registerBundles();
        $this->bundleDirs = $this->registerBundleDirs();

                $this->container = $this->initializeContainer();
        $this->container->setService('kernel', $this);

                foreach ($this->bundles as $bundle)
        {
            $bundle->boot($this->container);
        }

        $this->booted = true;

        return $this;
    }

    
    public function shutdown()
    {
        $this->booted = false;

        foreach ($this->bundles as $bundle)
        {
            $bundle->shutdown($this->container);
        }

        $this->container = null;
    }

    
    public function reboot()
    {
        $this->shutdown();
        $this->boot();
    }

    
    public function getRequest()
    {
        return $this->request;
    }

    
    public function handle(Request $request = null, $main = true, $raw = false)
    {
        if (false === $this->booted)
        {
            $this->boot();
        }

        if (null === $request)
        {
            $request = $this->container->getRequestService();
        }
        else
        {
            $this->container->setService('request', $request);
        }

        if (true === $main)
        {
            $this->request = $request;
        }

        return $this->container->getHttpKernelService()->handle($request, $main, $raw);
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

                $dumper = new PhpDumper($container);
        $content = $dumper->dump(array('class' => $class));
        if (!$this->debug)
        {
            $content = self::stripComments($content);
        }
        $this->writeCacheFile($file, $content);

        if ($this->debug)
        {
                        $parent = new \ReflectionObject($this);
            $configuration->addResource(new FileResource($parent->getFileName()));
            while ($parent = $parent->getParentClass())
            {
                $configuration->addResource(new FileResource($parent->getFileName()));
            }

                        $this->writeCacheFile($this->getCacheDir().'/'.$class.'.meta', serialize($configuration->getResources()));
        }
    }

    public function optimizeContainer(Builder $container)
    {
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
                        if (isset($token[1]))
            {
                                if (!isset($ignore[$token[0]]))
                {
                                        $output .= $token[1];
                }
            }
            else
            {
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


namespace Symfony\Foundation;

use Symfony\Components\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\DependencyInjection\ContainerInterface;




class EventDispatcher extends BaseEventDispatcher
{
    protected $container;

    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        foreach ($container->findAnnotatedServiceIds('kernel.listener') as $id => $attributes)
        {
            foreach ($attributes as $attribute)
            {
                if (isset($attribute['event']))
                {
                    $this->connect($attribute['event'], array($id, isset($attribute['method']) ? $attribute['method'] : 'handle'));
                }
            }
        }
    }

    
    public function getListeners($name)
    {
        if (!isset($this->listeners[$name]))
        {
            return array();
        }

        foreach ($this->listeners[$name] as $i => $listener)
        {
            if (is_array($listener) && is_string($listener[0]))
            {
                $this->listeners[$name][$i] = array($this->container->getService($listener[0]), $listener[1]);
            }
        }

        return $this->listeners[$name];
    }
}
