<?php 

namespace Symfony\Foundation\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\Console\Application;




abstract class Bundle implements BundleInterface
{
    protected $name;
    protected $namespacePrefix;
    protected $path;
    protected $reflection;

    
    public function buildContainer(ContainerInterface $container)
    {
    }

    
    public function boot(ContainerInterface $container)
    {
    }

    
    public function shutdown(ContainerInterface $container)
    {
    }

    
    public function getName()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->name;
    }

    
    public function getNamespacePrefix()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->namespacePrefix;
    }

    
    public function getPath()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->path;
    }

    
    public function getReflection()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->reflection;
    }

    
    public function registerCommands(Application $application)
    {
        foreach ($application->getKernel()->getBundleDirs() as $dir) {
            $bundleBase = dirname(str_replace('\\', '/', get_class($this)));
            $commandDir = $dir.'/'.basename($bundleBase).'/Command';
            if (!is_dir($commandDir)) {
                continue;
            }

                        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
                if ($file->isDir() || substr($file, -4) !== '.php') {
                    continue;
                }

                $class = str_replace('/', '\\', $bundleBase).'\\Command\\'.str_replace(realpath($commandDir).'/', '', basename(realpath($file), '.php'));

                $r = new \ReflectionClass($class);

                if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract()) {
                    $application->addCommand(new $class());
                }
            }
        }
    }

    protected function initReflection()
    {
        $tmp = dirname(str_replace('\\', '/', get_class($this)));
        $this->namespacePrefix = str_replace('/', '\\', dirname($tmp));
        $this->name = basename($tmp);
        $this->reflection = new \ReflectionObject($this);
        $this->path = dirname($this->reflection->getFilename());
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

        if ($container->getParameter('kernel.debug')) {
            $configuration->merge($loader->load('debug.xml'));
            $configuration->setDefinition('event_dispatcher', $configuration->findDefinition('debug.event_dispatcher'));
        }

        return $configuration;
    }

    
    public function boot(ContainerInterface $container)
    {
        $container->getErrorHandlerService();

                if ($container->getParameter('kernel.include_core_classes')) {
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

        if (isset($config['charset'])) {
            $configuration->setParameter('kernel.charset', $config['charset']);
        }

        if (!array_key_exists('compilation', $config)) {
            $classes = array(
                'Symfony\\Components\\Routing\\Router',
                'Symfony\\Components\\Routing\\RouterInterface',
                'Symfony\\Components\\EventDispatcher\\Event',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcherInterface',
                'Symfony\\Components\\Routing\\Matcher\\UrlMatcher',
                'Symfony\\Components\\HttpKernel\\HttpKernel',
                'Symfony\\Components\\HttpKernel\\Request',
                'Symfony\\Components\\HttpKernel\\Response',
                'Symfony\\Components\\HttpKernel\\Listener\\ResponseFilter',
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
                'Symfony\\Framework\\WebBundle\\Templating\\Engine',
            );
        } else {
            $classes = array();
            foreach (explode("\n", $config['compilation']) as $class) {
                if ($class) {
                    $classes[] = trim($class);
                }
            }
        }
        $configuration->setParameter('kernel.compiled_classes', $classes);

        if (array_key_exists('error_handler_level', $config)) {
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
        if (0 === $this->level) {
            return false;
        }

        if (error_reporting() & $level && $this->level & $level) {
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
        if ($autoReload) {
            $metadata = $cacheDir.'/'.$name.'.meta';
            if (!file_exists($metadata) || !file_exists($cache)) {
                $reload = true;
            } else {
                $time = filemtime($cache);
                $meta = unserialize(file_get_contents($metadata));

                if ($meta[1] != $classes) {
                    $reload = true;
                } else {
                    foreach ($meta[0] as $resource) {
                        if (!file_exists($resource) || filemtime($resource) > $time) {
                            $reload = true;

                            break;
                        }
                    }
                }
            }
        }

        if (!$reload && file_exists($cache)) {
            require_once $cache;

            return;
        }

        $files = array();
        $content = '';
        foreach ($classes as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class));
            }

            $r = new \ReflectionClass($class);
            $files[] = $r->getFileName();

            $content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName()));
        }

                if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true);
        }
        self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));

        if ($autoReload) {
                        self::writeCacheFile($metadata, serialize(array($files, $classes)));
        }
    }

    static protected function writeCacheFile($file, $content)
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

        if (!@rename($tmpFile, $file)) {
            throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
        }

        chmod($file, 0644);
    }
}


namespace Symfony\Foundation;

use Symfony\Components\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\DependencyInjection\ContainerInterface;




class EventDispatcher extends BaseEventDispatcher
{
    
    public function __construct(ContainerInterface $container)
    {
        foreach ($container->findAnnotatedServiceIds('kernel.listener') as $id => $attributes) {
            $container->getService($id)->register($this);
        }
    }
}
