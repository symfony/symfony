<?php
namespace Symfony\Component\DependencyInjection
{
interface ContainerInterface
{
    const EXCEPTION_ON_INVALID_REFERENCE = 1;
    const NULL_ON_INVALID_REFERENCE      = 2;
    const IGNORE_ON_INVALID_REFERENCE    = 3;
    const SCOPE_CONTAINER                = 'container';
    const SCOPE_PROTOTYPE                = 'prototype';
    function set($id, $service, $scope = self::SCOPE_CONTAINER);
    function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE);
    function has($id);
    function getParameter($name);
    function hasParameter($name);
    function setParameter($name, $value);
    function enterScope($name);
    function leaveScope($name);
    function addScope($name, $parentScope = self::SCOPE_CONTAINER);
    function hasScope($name);
    function isScopeActive($name);
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
    protected $scopes;
    protected $scopeChildren;
    protected $scopedServices;
    protected $scopeStacks;
    protected $loading = array();
    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        $this->parameterBag = null === $parameterBag ? new ParameterBag() : $parameterBag;
        $this->services =
        $this->scopes =
        $this->scopeChildren =
        $this->scopedServices =
        $this->scopeStacks = array();
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
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        if (self::SCOPE_PROTOTYPE === $scope) {
            throw new \InvalidArgumentException('You cannot set services of scope "prototype".');
        }
        $id = strtolower($id);
        if (self::SCOPE_CONTAINER !== $scope) {
            if (!isset($this->scopedServices[$scope])) {
                throw new \RuntimeException('You cannot set services of inactive scopes.');
            }
            $this->scopedServices[$scope][$id] = $service;
        }
        $this->services[$id] = $service;
    }
    public function has($id)
    {
        $id = strtolower($id);
        return isset($this->services[$id]) || method_exists($this, 'get'.strtr($id, array('_' => '', '.' => '_')).'Service');
    }
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $id = strtolower($id);
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        if (isset($this->loading[$id])) {
            throw new \LogicException(sprintf('Circular reference detected for service "%s" (services currently loading: %s).', $id, implode(', ', array_keys($this->loading))));
        }
        if (method_exists($this, $method = 'get'.strtr($id, array('_' => '', '.' => '_')).'Service')) {
            $this->loading[$id] = true;
            $service = $this->$method();
            unset($this->loading[$id]);
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
            if (preg_match('/^get(.+)Service$/', $method->getName(), $match)) {
                $ids[] = self::underscore($match[1]);
            }
        }
        return array_merge($ids, array_keys($this->services));
    }
    public function enterScope($name)
    {
        if (!isset($this->scopes[$name])) {
            throw new \InvalidArgumentException(sprintf('The scope "%s" does not exist.', $name));
        }
        if (self::SCOPE_CONTAINER !== $this->scopes[$name] && !isset($this->scopedServices[$this->scopes[$name]])) {
            throw new \RuntimeException(sprintf('The parent scope "%s" must be active when entering this scope.', $this->scopes[$name]));
        }
                                if (isset($this->scopedServices[$name])) {
            $services = array($this->services, $name => $this->scopedServices[$name]);
            unset($this->scopedServices[$name]);
            foreach ($this->scopeChildren[$name] as $child) {
                $services[$child] = $this->scopedServices[$child];
                unset($this->scopedServices[$child]);
            }
                        $this->services = call_user_func_array('array_diff_key', $services);
            array_shift($services);
                        if (!isset($this->scopeStacks[$name])) {
                $this->scopeStacks[$name] = new \SplStack();
            }
            $this->scopeStacks[$name]->push($services);
        }
        $this->scopedServices[$name] = array();
    }
    public function leaveScope($name)
    {
        if (!isset($this->scopedServices[$name])) {
            throw new \InvalidArgumentException(sprintf('The scope "%s" is not active.', $name));
        }
                        $services = array($this->services, $this->scopedServices[$name]);
        unset($this->scopedServices[$name]);
        foreach ($this->scopeChildren[$name] as $child) {
            if (!isset($this->scopedServices[$child])) {
                continue;
            }
            $services[] = $this->scopedServices[$child];
            unset($this->scopedServices[$child]);
        }
        $this->services = call_user_func_array('array_diff_key', $services);
                if (isset($this->scopeStacks[$name]) && count($this->scopeStacks[$name]) > 0) {
            $services = $this->scopeStacks[$name]->pop();
            $this->scopedServices += $services;
            array_unshift($services, $this->services);
            $this->services = call_user_func_array('array_merge', $services);
        }
    }
    public function addScope($name, $parentScope = self::SCOPE_CONTAINER)
    {
        if (self::SCOPE_CONTAINER === $name || self::SCOPE_PROTOTYPE === $name) {
            throw new \InvalidArgumentException(sprintf('The scope "%s" is reserved.', $name));
        }
        if (isset($this->scopes[$name])) {
            throw new \InvalidArgumentException(sprintf('A scope with name "%s" already exists.', $name));
        }
        if (self::SCOPE_CONTAINER !== $parentScope && !isset($this->scopes[$parentScope])) {
            throw new \InvalidArgumentException(sprintf('The parent scope "%s" does not exist, or is invalid.', $parentScope));
        }
        $this->scopes[$name] = $parentScope;
        $this->scopeChildren[$name] = array();
                while ($parentScope !== self::SCOPE_CONTAINER) {
            $this->scopeChildren[$parentScope][] = $name;
            $parentScope = $this->scopes[$parentScope];
        }
    }
    public function hasScope($name)
    {
        return isset($this->scopes[$name]);
    }
    public function isScopeActive($name)
    {
        return isset($this->scopedServices[$name]);
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
class ContainerAware implements ContainerAwareInterface
{
    protected $container;
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
}
namespace Symfony\Component\HttpKernel\Bundle
{
interface BundleInterface
{
    function boot();
    function shutdown();
    function getParent();
    function getName();
    function getNamespace();
    function getPath();
}
}
namespace Symfony\Component\HttpKernel\Bundle
{
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;
abstract class Bundle extends ContainerAware implements BundleInterface
{
    protected $name;
    protected $reflected;
    public function boot()
    {
    }
    public function shutdown()
    {
    }
    public function getNamespace()
    {
        if (null === $this->reflected) {
            $this->reflected = new \ReflectionObject($this);
        }
        return $this->reflected->getNamespaceName();
    }
    public function getPath()
    {
        if (null === $this->reflected) {
            $this->reflected = new \ReflectionObject($this);
        }
        return strtr(dirname($this->reflected->getFileName()), '\\', '/');
    }
    public function getParent()
    {
        return null;
    }
    final public function getName()
    {
        if (null !== $this->name) {
            return $this->name;
        }
        $name = get_class($this);
        $pos = strrpos($name, '\\');
        return $this->name = false === $pos ? $name :  substr($name, $pos + 1);
    }
    public function registerExtensions(ContainerBuilder $container)
    {
        if (!$dir = realpath($this->getPath().'/DependencyInjection')) {
            return;
        }
        $finder = new Finder();
        $finder->files()->name('*Extension.php')->in($dir);
        $prefix = $this->getNamespace().'\\DependencyInjection';
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
        $prefix = $this->getNamespace().'\\Command';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.$file->getBasename('.php'));
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->add($r->newInstance());
            }
        }
    }
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
use Symfony\Component\HttpFoundation\Request;
interface HttpKernelInterface
{
    const MASTER_REQUEST = 1;
    const SUB_REQUEST = 2;
    function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true);
}
}
namespace Symfony\Component\HttpKernel
{
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class HttpKernel implements HttpKernelInterface
{
    protected $dispatcher;
    protected $resolver;
    public function __construct(EventDispatcherInterface $dispatcher, ControllerResolverInterface $resolver)
    {
        $this->dispatcher = $dispatcher;
        $this->resolver = $resolver;
    }
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        try {
            $response = $this->handleRaw($request, $type);
        } catch (\Exception $e) {
            if (false === $catch) {
                throw $e;
            }
                        $event = new Event($this, 'core.exception', array('request_type' => $type, 'request' => $request, 'exception' => $e));
            $response = $this->dispatcher->notifyUntil($event);
            if (!$event->isProcessed()) {
                throw $e;
            }
            $response = $this->filterResponse($response, $request, 'A "core.exception" listener returned a non response object.', $type);
        }
        return $response;
    }
    protected function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
                $event = new Event($this, 'core.request', array('request_type' => $type, 'request' => $request));
        $response = $this->dispatcher->notifyUntil($event);
        if ($event->isProcessed()) {
            return $this->filterResponse($response, $request, 'A "core.request" listener returned a non response object.', $type);
        }
                if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException(sprintf('Unable to find the controller for "%s", check your route configuration.', $request->getPathInfo()));
        }
        $event = new Event($this, 'core.controller', array('request_type' => $type, 'request' => $request));
        $controller = $this->dispatcher->filter($event, $controller);
                if (!is_callable($controller)) {
            throw new \LogicException(sprintf('The controller must be a callable (%s given).', $this->varToString($controller)));
        }
                $arguments = $this->resolver->getArguments($request, $controller);
                $response = call_user_func_array($controller, $arguments);
                if (!$response instanceof Response) {
            $event = new Event($this, 'core.view', array('request_type' => $type, 'request' => $request));
            $response = $this->dispatcher->filter($event, $response);
        }
        return $this->filterResponse($response, $request, sprintf('The controller must return a response (%s given).', $this->varToString($response)), $type);
    }
    protected function filterResponse($response, $request, $message, $type)
    {
        if (!$response instanceof Response) {
            throw new \RuntimeException($message);
        }
        $response = $this->dispatcher->filter(new Event($this, 'core.response', array('request_type' => $type, 'request' => $request)), $response);
        if (!$response instanceof Response) {
            throw new \RuntimeException('A "core.response" listener returned a non response object.');
        }
        return $response;
    }
    protected function varToString($var)
    {
        if (is_object($var)) {
            return sprintf('[object](%s)', get_class($var));
        }
        if (is_array($var)) {
            $a = array();
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => %s', $k, $this->varToString($v));
            }
            return sprintf("[array](%s)", implode(', ', $a));
        }
        if (is_resource($var)) {
            return '[resource]';
        }
        return str_replace("\n", '', var_export((string) $var, true));
    }
}
}
namespace Symfony\Component\HttpKernel
{
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
interface KernelInterface extends HttpKernelInterface, \Serializable
{
    function registerRootDir();
    function registerBundles();
    function registerContainerConfiguration(LoaderInterface $loader);
    function boot();
    function shutdown();
    function getBundles();
    function isClassInActiveBundle($class);
    function getBundle($name, $first = true);
    function locateResource($name, $dir = null, $first = true);
    function getName();
    function getEnvironment();
    function isDebug();
    function getRootDir();
    function getContainer();
    function getStartTime();
    function getCacheDir();
    function getLogDir();
}
}
namespace Symfony\Component\HttpKernel
{
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
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
abstract class Kernel implements KernelInterface
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
    public function __construct($environment, $debug)
    {
        $this->environment = $environment;
        $this->debug = (Boolean) $debug;
        $this->booted = false;
        $this->rootDir = realpath($this->registerRootDir());
        $this->name = preg_replace('/[^a-zA-Z0-9_]+/', '', basename($this->rootDir));
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
    public function boot()
    {
        if (true === $this->booted) {
            return;
        }
                $this->initializeBundles();
                $this->initializeContainer();
        foreach ($this->bundles as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }
        $this->booted = true;
    }
    public function shutdown()
    {
        $this->booted = false;
        foreach ($this->bundles as $bundle) {
            $bundle->shutdown();
            $bundle->setContainer(null);
        }
        $this->container = null;
    }
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if (false === $this->booted) {
            $this->boot();
        }
        return $this->container->get('http_kernel')->handle($request, $type, $catch);
    }
    public function getBundles()
    {
        return $this->bundles;
    }
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
    protected function initializeBundles()
    {
                $this->bundles = array();
        $topMostBundles = array();
        $directChildren = array();
        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            if (isset($this->bundles[$name])) {
                throw new \LogicException(sprintf('Trying to register two bundles with the same name "%s"', $name));
            }
            $this->bundles[$name] = $bundle;
            if ($parentName = $bundle->getParent()) {
                if (isset($directChildren[$parentName])) {
                    throw new \LogicException(sprintf('Bundle "%s" is directly extended by two bundles "%s" and "%s".', $parentName, $name, $directChildren[$parentName]));
                }
                $directChildren[$parentName] = $name;
            } else {
                $topMostBundles[$name] = $bundle;
            }
        }
                if (count($diff = array_diff(array_keys($directChildren), array_keys($this->bundles)))) {
            throw new \LogicException(sprintf('Bundle "%s" extends bundle "%s", which is not registered.', $directChildren[$diff[0]], $diff[0]));
        }
                $this->bundleMap = array();
        foreach ($topMostBundles as $name => $bundle) {
            $bundleMap = array($bundle);
            $hierarchy = array($name);
            while (isset($directChildren[$name])) {
                $name = $directChildren[$name];
                array_unshift($bundleMap, $this->bundles[$name]);
                $hierarchy[] = $name;
            }
            foreach ($hierarchy as $bundle) {
                $this->bundleMap[$bundle] = $bundleMap;
                array_pop($bundleMap);
            }
        }
    }
    protected function initializeContainer()
    {
        $class = $this->name.ucfirst($this->environment).($this->debug ? 'Debug' : '').'ProjectContainer';
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
    protected function getKernelParameters()
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
                $dumper = new PhpDumper($container);
        $content = $dumper->dump(array('class' => $class));
        if (!$this->debug) {
            $content = self::stripComments($content);
        }
        $this->writeCacheFile($file, $content);
        if ($this->debug) {
            $container->addObjectResource($this);
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
}
namespace Symfony\Component\HttpFoundation
{
class ParameterBag
{
    protected $parameters;
    public function __construct(array $parameters = array())
    {
        $this->parameters = $parameters;
    }
    public function all()
    {
        return $this->parameters;
    }
    public function keys()
    {
        return array_keys($this->parameters);
    }
    public function replace(array $parameters = array())
    {
        $this->parameters = $parameters;
    }
    public function add(array $parameters = array())
    {
        $this->parameters = array_replace($this->parameters, $parameters);
    }
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }
    public function set($key, $value)
    {
        $this->parameters[$key] = $value;
    }
    public function has($key)
    {
        return array_key_exists($key, $this->parameters);
    }
    public function remove($key)
    {
        unset($this->parameters[$key]);
    }
    public function getAlpha($key, $default = '')
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
    }
    public function getAlnum($key, $default = '')
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
    }
    public function getDigits($key, $default = '')
    {
        return preg_replace('/[^[:digit:]]/', '', $this->get($key, $default));
    }
    public function getInt($key, $default = 0)
    {
        return (int) $this->get($key, $default);
    }
}
}
namespace Symfony\Component\HttpFoundation
{
use Symfony\Component\HttpFoundation\File\UploadedFile;
class FileBag extends ParameterBag
{
    private $fileKeys = array('error', 'name', 'size', 'tmp_name', 'type');
    public function __construct(array $parameters = array())
    {
                        parent::__construct();
        $this->replace($parameters);
    }
    public function replace(array $files = array())
    {
        $this->parameters = array();
        $this->add($files);
    }
    public function set($key, $value)
    {
        if (is_array($value) || $value instanceof UploadedFile) {
            parent::set($key, $this->convertFileInformation($value));
        }
    }
    public function add(array $files = array())
    {
        foreach ($files as $key => $file) {
            $this->set($key, $file);
        }
    }
    protected function convertFileInformation($file)
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }
        $file = $this->fixPhpFilesArray($file);
        if (is_array($file)) {
            $keys = array_keys($file);
            sort($keys);
            if ($keys == $this->fileKeys) {
                $file['error'] = (int) $file['error'];
            }
            if ($keys != $this->fileKeys) {
                $file = array_map(array($this, 'convertFileInformation'), $file);
            } else
                if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                    $file = null;
                } else {
                    $file = new UploadedFile($file['tmp_name'], $file['name'],
                    $file['type'], $file['size'], $file['error']);
                }
        }
        return $file;
    }
    protected function fixPhpFilesArray($data)
    {
        if (! is_array($data)) {
            return $data;
        }
        $keys = array_keys($data);
        sort($keys);
        if ($this->fileKeys != $keys || ! isset($data['name']) ||
         ! is_array($data['name'])) {
            return $data;
        }
        $files = $data;
        foreach ($this->fileKeys as $k) {
            unset($files[$k]);
        }
        foreach (array_keys($data['name']) as $key) {
            $files[$key] = $this->fixPhpFilesArray(array(
                'error'    => $data['error'][$key],
                'name'     => $data['name'][$key], 'type' => $data['type'][$key],
                'tmp_name' => $data['tmp_name'][$key],
                'size'     => $data['size'][$key]
            ));
        }
        return $files;
    }
}
}
namespace Symfony\Component\HttpFoundation
{
class ServerBag extends ParameterBag
{
    public function getHeaders()
    {
        $headers = array();
        foreach ($this->parameters as $key => $value) {
            if ('HTTP_' === substr($key, 0, 5)) {
                $headers[substr($key, 5)] = $value;
            }
        }
        return $headers;
    }
}
}
namespace Symfony\Component\HttpFoundation
{
class HeaderBag
{
    protected $headers;
    protected $cookies;
    protected $cacheControl;
    public function __construct(array $headers = array())
    {
        $this->cacheControl = array();
        $this->cookies = array();
        $this->headers = array();
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }
    public function all()
    {
        return $this->headers;
    }
    public function keys()
    {
        return array_keys($this->headers);
    }
    public function replace(array $headers = array())
    {
        $this->headers = array();
        $this->add($headers);
    }
    public function add(array $headers)
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }
    public function get($key, $default = null, $first = true)
    {
        $key = strtr(strtolower($key), '_', '-');
        if (!array_key_exists($key, $this->headers)) {
            if (null === $default) {
                return $first ? null : array();
            } else {
                return $first ? $default : array($default);
            }
        }
        if ($first) {
            return count($this->headers[$key]) ? $this->headers[$key][0] : $default;
        } else {
            return $this->headers[$key];
        }
    }
    public function set($key, $values, $replace = true)
    {
        $key = strtr(strtolower($key), '_', '-');
        if (!is_array($values)) {
            $values = array($values);
        }
        if (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_merge($this->headers[$key], $values);
        }
        if ('cache-control' === $key) {
            $this->cacheControl = $this->parseCacheControl($values[0]);
        }
    }
    public function has($key)
    {
        return array_key_exists(strtr(strtolower($key), '_', '-'), $this->headers);
    }
    public function contains($key, $value)
    {
        return in_array($value, $this->get($key, null, false));
    }
    public function remove($key)
    {
        $key = strtr(strtolower($key), '_', '-');
        unset($this->headers[$key]);
        if ('cache-control' === $key) {
            $this->cacheControl = array();
        }
    }
    public function setCookie(Cookie $cookie)
    {
        $this->cookies[$cookie->getName()] = $cookie;
    }
    public function removeCookie($name)
    {
        unset($this->cookies[$name]);
    }
    public function hasCookie($name)
    {
        return isset($this->cookies[$name]);
    }
    public function getCookie($name)
    {
        if (!$this->hasCookie($name)) {
            throw new \InvalidArgumentException(sprintf('There is no cookie with name "%s".', $name));
        }
        return $this->cookies[$name];
    }
    public function getCookies()
    {
        return $this->cookies;
    }
    public function getDate($key, \DateTime $default = null)
    {
        if (null === $value = $this->get($key)) {
            return $default;
        }
        if (false === $date = \DateTime::createFromFormat(DATE_RFC2822, $value)) {
            throw new \RuntimeException(sprintf('The %s HTTP header is not parseable (%s).', $key, $value));
        }
        return $date;
    }
    public function addCacheControlDirective($key, $value = true)
    {
        $this->cacheControl[$key] = $value;
        $this->set('Cache-Control', $this->getCacheControlHeader());
    }
    public function hasCacheControlDirective($key)
    {
        return array_key_exists($key, $this->cacheControl);
    }
    public function getCacheControlDirective($key)
    {
        return array_key_exists($key, $this->cacheControl) ? $this->cacheControl[$key] : null;
    }
    public function removeCacheControlDirective($key)
    {
        unset($this->cacheControl[$key]);
        $this->set('Cache-Control', $this->getCacheControlHeader());
    }
    protected function getCacheControlHeader()
    {
        $parts = array();
        ksort($this->cacheControl);
        foreach ($this->cacheControl as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"'.$value.'"';
                }
                $parts[] = "$key=$value";
            }
        }
        return implode(', ', $parts);
    }
    protected function parseCacheControl($header)
    {
        $cacheControl = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[strtolower($match[1])] = isset($match[2]) && $match[2] ? $match[2] : (isset($match[3]) ? $match[3] : true);
        }
        return $cacheControl;
    }
}
}
namespace Symfony\Component\HttpFoundation
{
use Symfony\Component\HttpFoundation\SessionStorage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
class Request
{
    public $attributes;
    public $request;
    public $query;
    public $server;
    public $files;
    public $cookies;
    public $headers;
    protected $content;
    protected $languages;
    protected $charsets;
    protected $acceptableContentTypes;
    protected $pathInfo;
    protected $requestUri;
    protected $baseUrl;
    protected $basePath;
    protected $method;
    protected $format;
    protected $session;
    static protected $formats;
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
    }
    public function initialize(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->request = new ParameterBag($request);
        $this->query = new ParameterBag($query);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->content = $content;
        $this->languages = null;
        $this->charsets = null;
        $this->acceptableContentTypes = null;
        $this->pathInfo = null;
        $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->method = null;
        $this->format = null;
    }
    static public function createfromGlobals()
    {
        return new static($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
    }
    static public function create($uri, $method = 'GET', $parameters = array(), $cookies = array(), $files = array(), $server = array(), $content = null)
    {
        $defaults = array(
            'SERVER_NAME'          => 'localhost',
            'SERVER_PORT'          => 80,
            'HTTP_HOST'            => 'localhost',
            'HTTP_USER_AGENT'      => 'Symfony/2.X',
            'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR'          => '127.0.0.1',
            'SCRIPT_NAME'          => '',
            'SCRIPT_FILENAME'      => '',
        );
        $components = parse_url($uri);
        if (isset($components['host'])) {
            $defaults['SERVER_NAME'] = $components['host'];
            $defaults['HTTP_HOST'] = $components['host'];
        }
        if (isset($components['scheme'])) {
            if ('https' === $components['scheme']) {
                $defaults['HTTPS'] = 'on';
                $defaults['SERVER_PORT'] = 443;
            }
        }
        if (isset($components['port'])) {
            $defaults['SERVER_PORT'] = $components['port'];
            $defaults['HTTP_HOST'] = $defaults['HTTP_HOST'].':'.$components['port'];
        }
        if (in_array(strtoupper($method), array('POST', 'PUT', 'DELETE'))) {
            $request = $parameters;
            $query = array();
            $defaults['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        } else {
            $request = array();
            $query = $parameters;
            if (false !== $pos = strpos($uri, '?')) {
                $qs = substr($uri, $pos + 1);
                parse_str($qs, $params);
                $query = array_merge($params, $query);
            }
        }
        $queryString = isset($components['query']) ? html_entity_decode($components['query']) : '';
        parse_str($queryString, $qs);
        if (is_array($qs)) {
            $query = array_replace($qs, $query);
        }
        $uri = $components['path'] . ($queryString ? '?'.$queryString : '');
        $server = array_replace($defaults, $server, array(
            'REQUEST_METHOD'       => strtoupper($method),
            'PATH_INFO'            => '',
            'REQUEST_URI'          => $uri,
            'QUERY_STRING'         => $queryString,
        ));
        return new static($query, $request, array(), $cookies, $files, $server, $content);
    }
    public function duplicate(array $query = null, array $request = null, array $attributes = null, array $cookies = null, array $files = null, array $server = null)
    {
        $dup = clone $this;
        $dup->initialize(
            null !== $query ? $query : $this->query->all(),
            null !== $request ? $request : $this->request->all(),
            null !== $attributes ? $attributes : $this->attributes->all(),
            null !== $cookies ? $cookies : $this->cookies->all(),
            null !== $files ? $files : $this->files->all(),
            null !== $server ? $server : $this->server->all()
        );
        return $dup;
    }
    public function __clone()
    {
        $this->query      = clone $this->query;
        $this->request    = clone $this->request;
        $this->attributes = clone $this->attributes;
        $this->cookies    = clone $this->cookies;
        $this->files      = clone $this->files;
        $this->server     = clone $this->server;
        $this->headers    = clone $this->headers;
    }
    public function overrideGlobals()
    {
        $_GET = $this->query->all();
        $_POST = $this->request->all();
        $_SERVER = $this->server->all();
        $_COOKIE = $this->cookies->all();
        foreach ($this->headers->all() as $key => $value) {
            $_SERVER['HTTP_'.strtoupper(str_replace('-', '_', $key))] = implode(', ', $value);
        }
                        $_REQUEST = array_merge($_GET, $_POST);
    }
                        public function get($key, $default = null)
    {
        return $this->query->get($key, $this->attributes->get($key, $this->request->get($key, $default)));
    }
    public function getSession()
    {
        return $this->session;
    }
    public function hasSession()
    {
        return $this->cookies->has(session_name());
    }
    public function setSession(Session $session)
    {
        $this->session = $session;
    }
    public function getClientIp($proxy = false)
    {
        if ($proxy) {
            if ($this->server->has('HTTP_CLIENT_IP')) {
                return $this->server->get('HTTP_CLIENT_IP');
            } elseif ($this->server->has('HTTP_X_FORWARDED_FOR')) {
                return $this->server->get('HTTP_X_FORWARDED_FOR');
            }
        }
        return $this->server->get('REMOTE_ADDR');
    }
    public function getScriptName()
    {
        return $this->server->get('SCRIPT_NAME', $this->server->get('ORIG_SCRIPT_NAME', ''));
    }
    public function getPathInfo()
    {
        if (null === $this->pathInfo) {
            $this->pathInfo = $this->preparePathInfo();
        }
        return $this->pathInfo;
    }
    public function getBasePath()
    {
        if (null === $this->basePath) {
            $this->basePath = $this->prepareBasePath();
        }
        return $this->basePath;
    }
    public function getBaseUrl()
    {
        if (null === $this->baseUrl) {
            $this->baseUrl = $this->prepareBaseUrl();
        }
        return $this->baseUrl;
    }
    public function getScheme()
    {
        return ($this->server->get('HTTPS') == 'on') ? 'https' : 'http';
    }
    public function getPort()
    {
        return $this->server->get('SERVER_PORT');
    }
    public function getHttpHost()
    {
        $host = $this->headers->get('HOST');
        if (!empty($host)) {
            return $host;
        }
        $scheme = $this->getScheme();
        $name   = $this->server->get('SERVER_NAME');
        $port   = $this->getPort();
        if (('http' == $scheme && $port == 80) || ('https' == $scheme && $port == 443)) {
            return $name;
        } else {
            return $name.':'.$port;
        }
    }
    public function getRequestUri()
    {
        if (null === $this->requestUri) {
            $this->requestUri = $this->prepareRequestUri();
        }
        return $this->requestUri;
    }
    public function getUri()
    {
        $qs = $this->getQueryString();
        if (null !== $qs) {
            $qs = '?'.$qs;
        }
        return $this->getScheme().'://'.$this->getHttpHost().$this->getBaseUrl().$this->getPathInfo().$qs;
    }
    public function getUriForPath($path)
    {
        return $this->getScheme().'://'.$this->getHttpHost().$this->getBaseUrl().$path;
    }
    public function getQueryString()
    {
        if (!$qs = $this->server->get('QUERY_STRING')) {
            return null;
        }
        $parts = array();
        $order = array();
        foreach (explode('&', $qs) as $segment) {
            if (false === strpos($segment, '=')) {
                $parts[] = $segment;
                $order[] = $segment;
            } else {
                $tmp = explode('=', urldecode($segment), 2);
                $parts[] = urlencode($tmp[0]).'='.urlencode($tmp[1]);
                $order[] = $tmp[0];
            }
        }
        array_multisort($order, SORT_ASC, $parts);
        return implode('&', $parts);
    }
    public function isSecure()
    {
        return (
            (strtolower($this->server->get('HTTPS')) == 'on' || $this->server->get('HTTPS') == 1)
            ||
            (strtolower($this->headers->get('SSL_HTTPS')) == 'on' || $this->headers->get('SSL_HTTPS') == 1)
            ||
            (strtolower($this->headers->get('X_FORWARDED_PROTO')) == 'https')
        );
    }
    public function getHost()
    {
        if ($host = $this->headers->get('X_FORWARDED_HOST')) {
            $elements = explode(',', $host);
            $host = trim($elements[count($elements) - 1]);
        } else {
            if (!$host = $this->headers->get('HOST')) {
                if (!$host = $this->server->get('SERVER_NAME')) {
                    $host = $this->server->get('SERVER_ADDR', '');
                }
            }
        }
                $elements = explode(':', $host);
        return trim($elements[0]);
    }
    public function setMethod($method)
    {
        $this->method = null;
        $this->server->set('REQUEST_METHOD', $method);
    }
    public function getMethod()
    {
        if (null === $this->method) {
            $this->method = strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
            if ('POST' === $this->method) {
                $this->method = strtoupper($this->request->get('_method', 'POST'));
            }
        }
        return $this->method;
    }
    public function getMimeType($format)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }
        return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
    }
    public function getFormat($mimeType)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }
        foreach (static::$formats as $format => $mimeTypes) {
            if (in_array($mimeType, (array) $mimeTypes)) {
                return $format;
            }
        }
        return null;
    }
    public function setFormat($format, $mimeTypes)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }
        static::$formats[$format] = is_array($mimeTypes) ? $mimeTypes : array($mimeTypes);
    }
    public function getRequestFormat()
    {
        if (null === $this->format) {
            $this->format = $this->get('_format', 'html');
        }
        return $this->format;
    }
    public function setRequestFormat($format)
    {
        $this->format = $format;
    }
    public function isMethodSafe()
    {
        return in_array($this->getMethod(), array('GET', 'HEAD'));
    }
    public function getContent($asResource = false)
    {
        if (false === $this->content || (true === $asResource && null !== $this->content)) {
            throw new \LogicException('getContent() can only be called once when using the resource return type.');
        }
        if (true === $asResource) {
            $this->content = false;
            return fopen('php://input', 'rb');
        }
        if (null === $this->content) {
            $this->content = file_get_contents('php://input');
        }
        return $this->content;
    }
    public function getETags()
    {
        return preg_split('/\s*,\s*/', $this->headers->get('if_none_match'), null, PREG_SPLIT_NO_EMPTY);
    }
    public function isNoCache()
    {
        return $this->headers->hasCacheControlDirective('no-cache') || 'no-cache' == $this->headers->get('Pragma');
    }
    public function getPreferredLanguage(array $locales = null)
    {
        $preferredLanguages = $this->getLanguages();
        if (null === $locales) {
            return isset($preferredLanguages[0]) ? $preferredLanguages[0] : null;
        }
        if (!$preferredLanguages) {
            return $locales[0];
        }
        $preferredLanguages = array_values(array_intersect($preferredLanguages, $locales));
        return isset($preferredLanguages[0]) ? $preferredLanguages[0] : $locales[0];
    }
    public function getLanguages()
    {
        if (null !== $this->languages) {
            return $this->languages;
        }
        $languages = $this->splitHttpAcceptHeader($this->headers->get('Accept-Language'));
        foreach ($languages as $lang) {
            if (strstr($lang, '-')) {
                $codes = explode('-', $lang);
                if ($codes[0] == 'i') {
                                                                                if (count($codes) > 1) {
                        $lang = $codes[1];
                    }
                } else {
                    for ($i = 0, $max = count($codes); $i < $max; $i++) {
                        if ($i == 0) {
                            $lang = strtolower($codes[0]);
                        } else {
                            $lang .= '_'.strtoupper($codes[$i]);
                        }
                    }
                }
            }
            $this->languages[] = $lang;
        }
        return $this->languages;
    }
    public function getCharsets()
    {
        if (null !== $this->charsets) {
            return $this->charsets;
        }
        return $this->charsets = $this->splitHttpAcceptHeader($this->headers->get('Accept-Charset'));
    }
    public function getAcceptableContentTypes()
    {
        if (null !== $this->acceptableContentTypes) {
            return $this->acceptableContentTypes;
        }
        return $this->acceptableContentTypes = $this->splitHttpAcceptHeader($this->headers->get('Accept'));
    }
    public function isXmlHttpRequest()
    {
        return 'XMLHttpRequest' == $this->headers->get('X-Requested-With');
    }
    public function splitHttpAcceptHeader($header)
    {
        if (!$header) {
            return array();
        }
        $values = array();
        foreach (array_filter(explode(',', $header)) as $value) {
                        if ($pos = strpos($value, ';')) {
                $q     = (float) trim(substr($value, strpos($value, '=') + 1));
                $value = trim(substr($value, 0, $pos));
            } else {
                $q = 1;
            }
            if (0 < $q) {
                $values[trim($value)] = $q;
            }
        }
        arsort($values);
        return array_keys($values);
    }
    protected function prepareRequestUri()
    {
        $requestUri = '';
        if ($this->headers->has('X_REWRITE_URL')) {
                        $requestUri = $this->headers->get('X_REWRITE_URL');
        } elseif ($this->server->get('IIS_WasUrlRewritten') == '1' && $this->server->get('UNENCODED_URL') != '') {
                        $requestUri = $this->server->get('UNENCODED_URL');
        } elseif ($this->server->has('REQUEST_URI')) {
            $requestUri = $this->server->get('REQUEST_URI');
                        $schemeAndHttpHost = $this->getScheme().'://'.$this->getHttpHost();
            if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
                        $requestUri = $this->server->get('ORIG_PATH_INFO');
            if ($this->server->get('QUERY_STRING')) {
                $requestUri .= '?'.$this->server->get('QUERY_STRING');
            }
        }
        return $requestUri;
    }
    protected function prepareBaseUrl()
    {
        $filename = basename($this->server->get('SCRIPT_FILENAME'));
        if (basename($this->server->get('SCRIPT_NAME')) === $filename) {
            $baseUrl = $this->server->get('SCRIPT_NAME');
        } elseif (basename($this->server->get('PHP_SELF')) === $filename) {
            $baseUrl = $this->server->get('PHP_SELF');
        } elseif (basename($this->server->get('ORIG_SCRIPT_NAME')) === $filename) {
            $baseUrl = $this->server->get('ORIG_SCRIPT_NAME');         } else {
                                    $path    = $this->server->get('PHP_SELF', '');
            $file    = $this->server->get('SCRIPT_FILENAME', '');
            $segs    = explode('/', trim($file, '/'));
            $segs    = array_reverse($segs);
            $index   = 0;
            $last    = count($segs);
            $baseUrl = '';
            do {
                $seg     = $segs[$index];
                $baseUrl = '/'.$seg.$baseUrl;
                ++$index;
            } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
        }
                $requestUri = $this->getRequestUri();
        if ($baseUrl && 0 === strpos($requestUri, $baseUrl)) {
                        return $baseUrl;
        }
        if ($baseUrl && 0 === strpos($requestUri, dirname($baseUrl))) {
                        return rtrim(dirname($baseUrl), '/');
        }
        $truncatedRequestUri = $requestUri;
        if (($pos = strpos($requestUri, '?')) !== false) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }
        $basename = basename($baseUrl);
        if (empty($basename) || !strpos($truncatedRequestUri, $basename)) {
                        return '';
        }
                                if ((strlen($requestUri) >= strlen($baseUrl)) && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }
        return rtrim($baseUrl, '/');
    }
    protected function prepareBasePath()
    {
        $filename = basename($this->server->get('SCRIPT_FILENAME'));
        $baseUrl = $this->getBaseUrl();
        if (empty($baseUrl)) {
            return '';
        }
        if (basename($baseUrl) === $filename) {
            $basePath = dirname($baseUrl);
        } else {
            $basePath = $baseUrl;
        }
        if ('\\' === DIRECTORY_SEPARATOR) {
            $basePath = str_replace('\\', '/', $basePath);
        }
        return rtrim($basePath, '/');
    }
    protected function preparePathInfo()
    {
        $baseUrl = $this->getBaseUrl();
        if (null === ($requestUri = $this->getRequestUri())) {
            return '';
        }
        $pathInfo = '';
                if ($pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        if ((null !== $baseUrl) && (false === ($pathInfo = substr($requestUri, strlen($baseUrl))))) {
                        return '';
        } elseif (null === $baseUrl) {
            return $requestUri;
        }
        return (string) $pathInfo;
    }
    static protected function initializeFormats()
    {
        static::$formats = array(
            'txt'  => array('text/plain'),
            'js'   => array('application/javascript', 'application/x-javascript', 'text/javascript'),
            'css'  => array('text/css'),
            'json' => array('application/json', 'application/x-json'),
            'xml'  => array('text/xml', 'application/xml', 'application/x-xml'),
            'rdf'  => array('application/rdf+xml'),
            'atom' => array('application/atom+xml'),
        );
    }
}
}
namespace Symfony\Component\HttpFoundation
{
class ApacheRequest extends Request
{
    protected function prepareRequestUri()
    {
        return $this->server->get('REQUEST_URI');
    }
    protected function prepareBaseUrl()
    {
        return $this->server->get('SCRIPT_NAME');
    }
    protected function preparePathInfo()
    {
        return $this->server->get('PATH_INFO');
    }
}
}
namespace Symfony\Component\ClassLoader
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
        self::writeCacheFile($cache, self::stripComments('<?php '.$content));
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
    static protected function stripComments($source)
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
                $output = preg_replace(array('/\s+$/Sm', '/\n+/S'), "\n", $output);
        return $output;
    }
}
}
namespace Symfony\Component\ClassLoader
{
class UniversalClassLoader
{
    protected $namespaces = array();
    protected $prefixes = array();
    protected $namespaceFallback = array();
    protected $prefixFallback = array();
    public function getNamespaces()
    {
        return $this->namespaces;
    }
    public function getPrefixes()
    {
        return $this->prefixes;
    }
    public function getNamespaceFallback()
    {
        return $this->namespaceFallback;
    }
    public function getPrefixFallback()
    {
        return $this->prefixFallback;
    }
    public function registerNamespaceFallback($dirs)
    {
        $this->namespaceFallback = (array) $dirs;
    }
    public function registerPrefixFallback($dirs)
    {
        $this->prefixFallback = (array) $dirs;
    }
    public function registerNamespaces(array $namespaces)
    {
        foreach ($namespaces as $namespace => $locations) {
            $this->namespaces[$namespace] = (array) $locations;
        }
    }
    public function registerNamespace($namespace, $paths)
    {
        $this->namespaces[$namespace] = (array) $paths;
    }
    public function registerPrefixes(array $classes)
    {
        foreach ($classes as $prefix => $locations) {
            $this->prefixes[$prefix] = (array) $locations;
        }
    }
    public function registerPrefix($prefix, $paths)
    {
        $this->prefixes[$prefix] = (array) $paths;
    }
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }
    public function loadClass($class)
    {
        $class = ltrim($class, '\\');
        if (false !== ($pos = strrpos($class, '\\'))) {
                        $namespace = substr($class, 0, $pos);
            foreach ($this->namespaces as $ns => $dirs) {
                foreach ($dirs as $dir) {
                    if (0 === strpos($namespace, $ns)) {
                        $className = substr($class, $pos + 1);
                        $file = $dir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
                        if (file_exists($file)) {
                            require $file;
                            return;
                        }
                    }
                }
            }
            foreach ($this->namespaceFallback as $dir) {
                $file = $dir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        } else {
                        foreach ($this->prefixes as $prefix => $dirs) {
                foreach ($dirs as $dir) {
                    if (0 === strpos($class, $prefix)) {
                        $file = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
                        if (file_exists($file)) {
                            require $file;
                            return;
                        }
                    }
                }
            }
            foreach ($this->prefixFallback as $dir) {
                $file = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        }
    }
}
}
namespace Symfony\Component\ClassLoader
{
class MapFileClassLoader
{
    protected $map = array();
    public function __construct($file)
    {
        $this->map = require $file;
    }
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }
    public function loadClass($class)
    {
        if ('\\' === $class[0]) {
            $class = substr($class, 1);
        }
        if (isset($this->map[$class])) {
            require $this->map[$class];
        }
    }
}
}
