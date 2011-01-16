<?php
namespace Symfony\Component\HttpKernel\Bundle
{
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;
abstract class Bundle extends ContainerAware implements BundleInterface
{
    protected $name;
    protected $namespace;
    protected $path;
    protected $reflection;
    public function boot()
    {
    }
    public function shutdown()
    {
    }
    public function getName()
    {
        if (null === $this->name) {
            $this->initReflection();
        }
        return $this->name;
    }
    public function getNamespace()
    {
        if (null === $this->name) {
            $this->initReflection();
        }
        return $this->namespace;
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
    public function registerExtensions(ContainerBuilder $container)
    {
        if (!$dir = realpath($this->getPath().'/DependencyInjection')) {
            return;
        }
        $finder = new Finder();
        $finder->files()->name('*Extension.php')->in($dir);
        $prefix = $this->namespace.'\\DependencyInjection';
        foreach ($finder as $file) {
            $class = $prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.$file->getBasename('.php');
            $container->registerExtension(new $class());
        }
    }
    public function registerCommands(Application $application)
    {
        if (!$dir = realpath($this->getPath().'/Command')) {
            return;
        }
        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);
        $prefix = $this->namespace.'\\Command';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.$file->getBasename('.php'));
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->add($r->newInstance());
            }
        }
    }
    protected function initReflection()
    {
        $this->reflection = new \ReflectionObject($this);
        $this->namespace = $this->reflection->getNamespaceName();
        $this->name = $this->reflection->getShortName();
        $this->path = str_replace('\\', '/', dirname($this->reflection->getFilename()));
    }
}
}
namespace Symfony\Component\HttpKernel\Bundle
{
interface BundleInterface
{
    function boot();
    function shutdown();
}
}
namespace Symfony\Component\HttpKernel\Debug
{
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
}
namespace Symfony\Component\HttpKernel
{
class ClassCollectionLoader
{
    static protected $loaded;
    static public function load($classes, $cacheDir, $name, $autoReload, $adaptive = false)
    {
                if (isset(self::$loaded[$name])) {
            return;
        }
        self::$loaded[$name] = true;
        $classes = array_unique($classes);
        if ($adaptive) {
                        $classes = array_diff($classes, get_declared_classes(), get_declared_interfaces());
                        $name = $name.'-'.substr(md5(implode('|', $classes)), 0, 5);
        }
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
            $c = preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName()));
                        if (!$r->inNamespace()) {
                $c = "\nnamespace\n{\n$c\n}\n";
            } else {
                $c = self::fixNamespaceDeclarations('<?php '.$c);
                $c = preg_replace('/^\s*<\?php/', '', $c);
            }
            $content .= $c;
        }
                if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true);
        }
        self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));
        if ($autoReload) {
                        self::writeCacheFile($metadata, serialize(array($files, $classes)));
        }
    }
    static public function fixNamespaceDeclarations($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }
        $output = '';
        $inNamespace = false;
        $tokens = token_get_all($source);
        while ($token = array_shift($tokens)) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (T_NAMESPACE === $token[0]) {
                if ($inNamespace) {
                    $output .= "}\n";
                }
                $output .= $token[1];
                                while (($t = array_shift($tokens)) && is_array($t) && in_array($t[0], array(T_WHITESPACE, T_NS_SEPARATOR, T_STRING))) {
                    $output .= $t[1];
                }
                if (is_string($t) && '{' === $t) {
                    $inNamespace = false;
                    array_unshift($tokens, $t);
                } else {
                    $output .= "\n{";
                    $inNamespace = true;
                }
            } else {
                $output .= $token[1];
            }
        }
        if ($inNamespace) {
            $output .= "}\n";
        }
        return $output;
    }
    static protected function writeCacheFile($file, $content)
    {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            chmod($file, 0644);
            return;
        }
        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }
}
}
namespace Symfony\Component\DependencyInjection
{
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
class Container implements ContainerInterface
{
    protected $parameterBag;
    protected $services;
    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        $this->parameterBag = null === $parameterBag ? new ParameterBag() : $parameterBag;
        $this->services = array();
        $this->set('service_container', $this);
    }
    public function compile()
    {
        $this->parameterBag->resolve();
        $this->parameterBag = new FrozenParameterBag($this->parameterBag->all());
    }
    public function isFrozen()
    {
        return $this->parameterBag instanceof FrozenParameterBag;
    }
    public function getParameterBag()
    {
        return $this->parameterBag;
    }
    public function getParameter($name)
    {
        return $this->parameterBag->get($name);
    }
    public function hasParameter($name)
    {
        return $this->parameterBag->has($name);
    }
    public function setParameter($name, $value)
    {
        $this->parameterBag->set($name, $value);
    }
    public function set($id, $service)
    {
        $this->services[strtolower($id)] = $service;
    }
    public function has($id)
    {
        $id = strtolower($id);
        return isset($this->services[$id]) || method_exists($this, 'get'.strtr($id, array('_' => '', '.' => '_')).'Service');
    }
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        static $loading = array();
        $id = strtolower($id);
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        if (isset($loading[$id])) {
            throw new \LogicException(sprintf('Circular reference detected for service "%s" (services currently loading: %s).', $id, implode(', ', array_keys($loading))));
        }
        if (method_exists($this, $method = 'get'.strtr($id, array('_' => '', '.' => '_')).'Service')) {
            $loading[$id] = true;
            $service = $this->$method();
            unset($loading[$id]);
            return $service;
        }
        if (self::EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior) {
            throw new \InvalidArgumentException(sprintf('The service "%s" does not exist.', $id));
        }
    }
    public function getServiceIds()
    {
        $ids = array();
        $r = new \ReflectionClass($this);
        foreach ($r->getMethods() as $method) {
            if (preg_match('/^get(.+)Service$/', $name = $method->getName(), $match)) {
                $ids[] = self::underscore($match[1]);
            }
        }
        return array_merge($ids, array_keys($this->services));
    }
    static public function camelize($id)
    {
        return preg_replace(array('/(?:^|_)+(.)/e', '/\.(.)/e'), array("strtoupper('\\1')", "'_'.strtoupper('\\1')"), $id);
    }
    static public function underscore($id)
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }
}
}
namespace Symfony\Component\DependencyInjection
{
interface ContainerAwareInterface
{
    function setContainer(ContainerInterface $container = null);
}
}
namespace Symfony\Component\DependencyInjection
{
interface ContainerInterface
{
    const EXCEPTION_ON_INVALID_REFERENCE = 1;
    const NULL_ON_INVALID_REFERENCE      = 2;
    const IGNORE_ON_INVALID_REFERENCE    = 3;
    function set($id, $service);
    function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE);
    function has($id);
}
}
namespace Symfony\Component\DependencyInjection\ParameterBag
{
class FrozenParameterBag extends ParameterBag
{
    public function __construct(array $parameters = array())
    {
        foreach ($parameters as $key => $value) {
            $this->parameters[strtolower($key)] = $value;
        }
    }
    public function clear()
    {
        throw new \LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }
    public function add(array $parameters)
    {
        throw new \LogicException('Impossible to call add() on a frozen ParameterBag.');
    }
    public function set($name, $value)
    {
        throw new \LogicException('Impossible to call set() on a frozen ParameterBag.');
    }
}
}
namespace Symfony\Component\DependencyInjection\ParameterBag
{
interface ParameterBagInterface
{
    function clear();
    function add(array $parameters);
    function all();
    function get($name);
    function set($name, $value);
    function has($name);
}
}
namespace Symfony\Component\DependencyInjection
{
interface TaggedContainerInterface extends ContainerInterface
{
    function findTaggedServiceIds($name);
}
}
