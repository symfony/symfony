<?php
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
namespace Symfony\Component\HttpKernel\HttpCache
{
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class HttpCache implements HttpKernelInterface
{
    protected $kernel;
    protected $traces;
    protected $store;
    protected $request;
    protected $esi;
    public function __construct(HttpKernelInterface $kernel, StoreInterface $store, Esi $esi = null, array $options = array())
    {
        $this->store = $store;
        $this->kernel = $kernel;
                register_shutdown_function(array($this->store, 'cleanup'));
        $this->options = array_merge(array(
            'debug'                  => false,
            'default_ttl'            => 0,
            'private_headers'        => array('Authorization', 'Cookie'),
            'allow_reload'           => false,
            'allow_revalidate'       => false,
            'stale_while_revalidate' => 2,
            'stale_if_error'         => 60,
        ), $options);
        $this->esi = $esi;
    }
    public function getTraces()
    {
        return $this->traces;
    }
    public function getLog()
    {
        $log = array();
        foreach ($this->traces as $request => $traces) {
            $log[] = sprintf('%s: %s', $request, implode(', ', $traces));
        }
        return implode('; ', $log);
    }
    public function getRequest()
    {
        return $this->request;
    }
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
                if (HttpKernelInterface::MASTER_REQUEST === $type) {
            $this->traces = array();
            $this->request = $request;
        }
        $path = $request->getPathInfo();
        if ($qs = $request->getQueryString()) {
            $path .= '?'.$qs;
        }
        $this->traces[$request->getMethod().' '.$path] = array();
        if (!$request->isMethodSafe($request)) {
            $response = $this->invalidate($request, $catch);
        } elseif ($request->headers->has('expect')) {
            $response = $this->pass($request, $catch);
        } else {
            $response = $this->lookup($request, $catch);
        }
        $response->isNotModified($request);
        $this->restoreResponseBody($request, $response);
        if (HttpKernelInterface::MASTER_REQUEST === $type && $this->options['debug']) {
            $response->headers->set('X-Symfony-Cache', $this->getLog());
        }
        return $response;
    }
    protected function pass(Request $request, $catch = false)
    {
        $this->record($request, 'pass');
        return $this->forward($request, $catch);
    }
    protected function invalidate(Request $request, $catch = false)
    {
        $response = $this->pass($request, $catch);
                if ($response->isSuccessful() || $response->isRedirect()) {
            try {
                $this->store->invalidate($request, $catch);
                $this->record($request, 'invalidate');
            } catch (\Exception $e) {
                $this->record($request, 'invalidate-failed');
                if ($this->options['debug']) {
                    throw $e;
                }
            }
        }
        return $response;
    }
    protected function lookup(Request $request, $catch = false)
    {
                if ($this->options['allow_reload'] && $request->isNoCache()) {
            $this->record($request, 'reload');
            return $this->fetch($request);
        }
        try {
            $entry = $this->store->lookup($request);
        } catch (\Exception $e) {
            $this->record($request, 'lookup-failed');
            if ($this->options['debug']) {
                throw $e;
            }
            return $this->pass($request, $catch);
        }
        if (null === $entry) {
            $this->record($request, 'miss');
            return $this->fetch($request, $catch);
        }
        if (!$this->isFreshEnough($request, $entry)) {
            $this->record($request, 'stale');
            return $this->validate($request, $entry);
        }
        $this->record($request, 'fresh');
        $entry->headers->set('Age', $entry->getAge());
        return $entry;
    }
    protected function validate(Request $request, Response $entry)
    {
        $subRequest = clone $request;
                $subRequest->setMethod('get');
                $subRequest->headers->set('if_modified_since', $entry->headers->get('Last-Modified'));
                                $cachedEtags = array($entry->getEtag());
        $requestEtags = $request->getEtags();
        $etags = array_unique(array_merge($cachedEtags, $requestEtags));
        $subRequest->headers->set('if_none_match', $etags ? implode(', ', $etags) : '');
        $response = $this->forward($subRequest, false, $entry);
        if (304 == $response->getStatusCode()) {
            $this->record($request, 'valid');
                        $etag = $response->getEtag();
            if ($etag && in_array($etag, $requestEtags) && !in_array($etag, $cachedEtags)) {
                return $response;
            }
            $entry = clone $entry;
            $entry->headers->remove('Date');
            foreach (array('Date', 'Expires', 'Cache-Control', 'ETag', 'Last-Modified') as $name) {
                if ($response->headers->has($name)) {
                    $entry->headers->set($name, $response->headers->get($name));
                }
            }
            $response = $entry;
        } else {
            $this->record($request, 'invalid');
        }
        if ($response->isCacheable()) {
            $this->store($request, $response);
        }
        return $response;
    }
    protected function fetch(Request $request, $catch = false)
    {
        $subRequest = clone $request;
                $subRequest->setMethod('get');
                $subRequest->headers->remove('if_modified_since');
        $subRequest->headers->remove('if_none_match');
        $response = $this->forward($subRequest, $catch);
        if ($this->isPrivateRequest($request) && !$response->headers->hasCacheControlDirective('public')) {
            $response->setPrivate(true);
        } elseif ($this->options['default_ttl'] > 0 && null === $response->getTtl() && !$response->headers->getCacheControlDirective('must-revalidate')) {
            $response->setTtl($this->options['default_ttl']);
        }
        if ($response->isCacheable()) {
            $this->store($request, $response);
        }
        return $response;
    }
    protected function forward(Request $request, $catch = false, Response $entry = null)
    {
        if ($this->esi) {
            $this->esi->addSurrogateEsiCapability($request);
        }
                $response = $this->kernel->handle($request, HttpKernelInterface::MASTER_REQUEST, $catch);
                if (null !== $entry && in_array($response->getStatusCode(), array(500, 502, 503, 504))) {
            if (null === $age = $entry->headers->getCacheControlDirective('stale-if-error')) {
                $age = $this->options['stale_if_error'];
            }
            if (abs($entry->getTtl()) < $age) {
                $this->record($request, 'stale-if-error');
                return $entry;
            }
        }
        $this->processResponseBody($request, $response);
        return $response;
    }
    protected function isFreshEnough(Request $request, Response $entry)
    {
        if (!$entry->isFresh()) {
            return $this->lock($request, $entry);
        }
        if ($this->options['allow_revalidate'] && null !== $maxAge = $request->headers->getCacheControlDirective('max-age')) {
            return $maxAge > 0 && $maxAge >= $entry->getAge();
        }
        return true;
    }
    protected function lock(Request $request, Response $entry)
    {
                $lock = $this->store->lock($request, $entry);
                if (true !== $lock) {
                        if (null === $age = $entry->headers->getCacheControlDirective('stale-while-revalidate')) {
                $age = $this->options['stale_while_revalidate'];
            }
            if (abs($entry->getTtl()) < $age) {
                $this->record($request, 'stale-while-revalidate');
                                return true;
            } else {
                                $wait = 0;
                while (file_exists($lock) && $wait < 5000000) {
                    usleep($wait += 50000);
                }
                if ($wait < 2000000) {
                                        $new = $this->lookup($request);
                    $entry->headers = $new->headers;
                    $entry->setContent($new->getContent());
                    $entry->setStatusCode($new->getStatusCode());
                    $entry->setProtocolVersion($new->getProtocolVersion());
                    $entry->setCookies($new->getCookies());
                    return true;
                } else {
                                        $entry->setStatusCode(503);
                    $entry->setContent('503 Service Unavailable');
                    $entry->headers->set('Retry-After', 10);
                    return true;
                }
            }
        }
                return false;
    }
    protected function store(Request $request, Response $response)
    {
        try {
            $this->store->write($request, $response);
            $this->record($request, 'store');
            $response->headers->set('Age', $response->getAge());
        } catch (\Exception $e) {
            $this->record($request, 'store-failed');
            if ($this->options['debug']) {
                throw $e;
            }
        }
                $this->store->unlock($request);
    }
    protected function restoreResponseBody(Request $request, Response $response)
    {
        if ('head' === strtolower($request->getMethod())) {
            $response->setContent('');
            $response->headers->remove('X-Body-Eval');
            $response->headers->remove('X-Body-File');
            return;
        }
        if ($response->headers->has('X-Body-Eval')) {
            ob_start();
            if ($response->headers->has('X-Body-File')) {
                include $response->headers->get('X-Body-File');
            } else {
                eval('; ?>'.$response->getContent().'<?php ;');
            }
            $response->setContent(ob_get_clean());
            $response->headers->remove('X-Body-Eval');
        } elseif ($response->headers->has('X-Body-File')) {
            $response->setContent(file_get_contents($response->headers->get('X-Body-File')));
        } else {
            return;
        }
        $response->headers->remove('X-Body-File');
        if (!$response->headers->has('Transfer-Encoding')) {
            $response->headers->set('Content-Length', strlen($response->getContent()));
        }
    }
    protected function processResponseBody(Request $request, Response $response)
    {
        if (null !== $this->esi && $this->esi->needsEsiParsing($response)) {
            $this->esi->process($request, $response);
        }
    }
    protected function isPrivateRequest(Request $request)
    {
        foreach ($this->options['private_headers'] as $key) {
            $key = strtolower(str_replace('HTTP_', '', $key));
            if ('cookie' === $key) {
                if (count($request->cookies->all())) {
                    return true;
                }
            } elseif ($request->headers->has($key)) {
                return true;
            }
        }
        return false;
    }
    protected function record(Request $request, $event)
    {
        $path = $request->getPathInfo();
        if ($qs = $request->getQueryString()) {
            $path .= '?'.$qs;
        }
        $this->traces[$request->getMethod().' '.$path][] = $event;
    }
}
}
namespace Symfony\Component\HttpKernel\HttpCache
{
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderBag;
interface StoreInterface
{
    function lookup(Request $request);
    function write(Request $request, Response $response);
    function invalidate(Request $request);
    function lock(Request $request);
    function unlock(Request $request);
    function purge($url);
    function cleanup();
}
}
namespace Symfony\Component\HttpKernel\HttpCache
{
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderBag;
class Store implements StoreInterface
{
    protected $root;
    protected $keyCache;
    protected $locks;
    public function __construct($root)
    {
        $this->root = $root;
        if (!is_dir($this->root)) {
            mkdir($this->root, 0777, true);
        }
        $this->keyCache = new \SplObjectStorage();
        $this->locks = array();
    }
    public function cleanup()
    {
                foreach ($this->locks as $lock) {
            @unlink($lock);
        }
        $error = error_get_last();
        if (1 === $error['type'] && false === headers_sent()) {
                        header('HTTP/1.0 503 Service Unavailable');
            header('Retry-After: 10');
            echo '503 Service Unavailable';
        }
    }
    public function lock(Request $request)
    {
        if (false !== $lock = @fopen($path = $this->getPath($this->getCacheKey($request).'.lck'), 'x')) {
            fclose($lock);
            $this->locks[] = $path;
            return true;
        } else {
            return $path;
        }
    }
    public function unlock(Request $request)
    {
        return @unlink($this->getPath($this->getCacheKey($request).'.lck'));
    }
    public function lookup(Request $request)
    {
        $key = $this->getCacheKey($request);
        if (!$entries = $this->getMetadata($key)) {
            return null;
        }
                $match = null;
        foreach ($entries as $entry) {
            if ($this->requestsMatch(isset($entry[1]['vary']) ? $entry[1]['vary'][0] : '', $request->headers->all(), $entry[0])) {
                $match = $entry;
                break;
            }
        }
        if (null === $match) {
            return null;
        }
        list($req, $headers) = $match;
        if (file_exists($body = $this->getPath($headers['x-content-digest'][0]))) {
            return $this->restoreResponse($headers, $body);
        } else {
                                                return null;
        }
    }
    public function write(Request $request, Response $response)
    {
        $key = $this->getCacheKey($request);
        $storedEnv = $this->persistRequest($request);
                if (!$response->headers->has('X-Content-Digest')) {
            $digest = 'en'.sha1($response->getContent());
            if (false === $this->save($digest, $response->getContent())) {
                throw new \RuntimeException('Unable to store the entity.');
            }
            $response->headers->set('X-Content-Digest', $digest);
            if (!$response->headers->has('Transfer-Encoding')) {
                $response->headers->set('Content-Length', strlen($response->getContent()));
            }
        }
                $entries = array();
        $vary = $response->headers->get('vary');
        foreach ($this->getMetadata($key) as $entry) {
            if (!isset($entry[1]['vary'])) {
                $entry[1]['vary'] = array('');
            }
            if ($vary != $entry[1]['vary'][0] || !$this->requestsMatch($vary, $entry[0], $storedEnv)) {
                $entries[] = $entry;
            }
        }
        $headers = $this->persistResponse($response);
        unset($headers['age']);
        array_unshift($entries, array($storedEnv, $headers));
        if (false === $this->save($key, serialize($entries))) {
            throw new \RuntimeException('Unable to store the metadata.');
        }
        return $key;
    }
    public function invalidate(Request $request)
    {
        $modified = false;
        $key = $this->getCacheKey($request);
        $entries = array();
        foreach ($this->getMetadata($key) as $entry) {
            $response = $this->restoreResponse($entry[1]);
            if ($response->isFresh()) {
                $response->expire();
                $modified = true;
                $entries[] = array($entry[0], $this->persistResponse($response));
            } else {
                $entries[] = $entry;
            }
        }
        if ($modified) {
            if (false === $this->save($key, serialize($entries))) {
                throw new \RuntimeException('Unable to store the metadata.');
            }
        }
                foreach (array('Location', 'Content-Location') as $header) {
            if ($uri = $request->headers->get($header)) {
                $subRequest = Request::create($uri, 'get', array(), array(), array(), $request->server->all());
                $this->invalidate($subRequest);
            }
        }
    }
    protected function requestsMatch($vary, $env1, $env2)
    {
        if (empty($vary)) {
            return true;
        }
        foreach (preg_split('/[\s,]+/', $vary) as $header) {
            $key = strtr(strtolower($header), '_', '-');
            $v1 = isset($env1[$key]) ? $env1[$key] : null;
            $v2 = isset($env2[$key]) ? $env2[$key] : null;
            if ($v1 !== $v2) {
                return false;
            }
        }
        return true;
    }
    protected function getMetadata($key)
    {
        if (false === $entries = $this->load($key)) {
            return array();
        }
        return unserialize($entries);
    }
    public function purge($url)
    {
        if (file_exists($path = $this->getPath($this->getCacheKey(Request::create($url))))) {
            unlink($path);
            return true;
        }
        return false;
    }
    protected function load($key)
    {
        $path = $this->getPath($key);
        return file_exists($path) ? file_get_contents($path) : false;
    }
    protected function save($key, $data)
    {
        $path = $this->getPath($key);
        if (!is_dir(dirname($path)) && false === @mkdir(dirname($path), 0777, true)) {
            return false;
        }
        $tmpFile = tempnam(dirname($path), basename($path));
        if (false === $fp = @fopen($tmpFile, 'wb')) {
            return false;
        }
        @fwrite($fp, $data);
        @fclose($fp);
        if ($data != file_get_contents($tmpFile)) {
            return false;
        }
        if (false === @rename($tmpFile, $path)) {
            return false;
        }
        chmod($path, 0644);
    }
    public function getPath($key)
    {
        return $this->root.DIRECTORY_SEPARATOR.substr($key, 0, 2).DIRECTORY_SEPARATOR.substr($key, 2, 2).DIRECTORY_SEPARATOR.substr($key, 4, 2).DIRECTORY_SEPARATOR.substr($key, 6);
    }
    protected function getCacheKey(Request $request)
    {
        if (isset($this->keyCache[$request])) {
            return $this->keyCache[$request];
        }
        return $this->keyCache[$request] = 'md'.sha1($request->getUri());
    }
    protected function persistRequest(Request $request)
    {
        return $request->headers->all();
    }
    protected function persistResponse(Response $response)
    {
        $headers = $response->headers->all();
        $headers['X-Status'] = array($response->getStatusCode());
        return $headers;
    }
    protected function restoreResponse($headers, $body = null)
    {
        $status = $headers['X-Status'][0];
        unset($headers['X-Status']);
        if (null !== $body) {
            $headers['X-Body-File'] = array($body);
        }
        return new Response($body, $status, $headers);
    }
}
}
namespace Symfony\Component\HttpKernel\HttpCache
{
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
class Esi
{
    protected $contentTypes;
    public function __construct(array $contentTypes = array('text/html', 'text/xml', 'application/xml'))
    {
        $this->contentTypes = $contentTypes;
    }
    public function hasSurrogateEsiCapability(Request $request)
    {
        if (null === $value = $request->headers->get('Surrogate-Capability')) {
            return false;
        }
        return (Boolean) preg_match('#ESI/1.0#', $value);
    }
    public function addSurrogateEsiCapability(Request $request)
    {
        $current = $request->headers->get('Surrogate-Capability');
        $new = 'symfony2="ESI/1.0"';
        $request->headers->set('Surrogate-Capability', $current ? $current.', '.$new : $new);
    }
    public function addSurrogateControl(Response $response)
    {
        if (false !== strpos($response->getContent(), '<esi:include')) {
            $response->headers->set('Surrogate-Control', 'content="ESI/1.0"');
        }
    }
    public function needsEsiParsing(Response $response)
    {
        if (!$control = $response->headers->get('Surrogate-Control')) {
            return false;
        }
        return (Boolean) preg_match('#content="[^"]*ESI/1.0[^"]*"#', $control);
    }
    public function renderIncludeTag($uri, $alt = null, $ignoreErrors = true, $comment = '')
    {
        $html = sprintf('<esi:include src="%s"%s%s />',
            $uri,
            $ignoreErrors ? ' onerror="continue"' : '',
            $alt ? sprintf(' alt="%s"', $alt) : ''
        );
        if (!empty($comment)) {
            return sprintf("<esi:comment text=\"%s\" />\n%s", $comment, $html);
        }
        return $html;
    }
    public function process(Request $request, Response $response)
    {
        $this->request = $request;
        $type = $response->headers->get('Content-Type');
        if (empty($type)) {
            $type = 'text/html';
        }
        $parts = explode(';', $type);
        if (!in_array($parts[0], $this->contentTypes)) {
            return $response;
        }
                $content = $response->getContent();
        $content = preg_replace_callback('#<esi\:include\s+(.*?)\s*/>#', array($this, 'handleEsiIncludeTag'), $content);
        $content = preg_replace('#<esi\:comment[^>]*/>#', '', $content);
        $content = preg_replace('#<esi\:remove>.*?</esi\:remove>#', '', $content);
        $response->setContent($content);
        $response->headers->set('X-Body-Eval', 'ESI');
                if ($response->headers->has('Surrogate-Control')) {
            $value = $response->headers->get('Surrogate-Control');
            if ('content="ESI/1.0"' == $value) {
                $response->headers->remove('Surrogate-Control');
            } elseif (preg_match('#,\s*content="ESI/1.0"#', $value)) {
                $response->headers->set('Surrogate-Control', preg_replace('#,\s*content="ESI/1.0"#', '', $value));
            } elseif (preg_match('#content="ESI/1.0",\s*#', $value)) {
                $response->headers->set('Surrogate-Control', preg_replace('#content="ESI/1.0",\s*#', '', $value));
            }
        }
    }
    public function handle(HttpCache $cache, $uri, $alt, $ignoreErrors)
    {
        $subRequest = Request::create($uri, 'get', array(), $cache->getRequest()->cookies->all(), array(), $cache->getRequest()->server->all());
        try {
            $response = $cache->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);
            if (200 != $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $response->getStatusCode()));
            }
            return $response->getContent();
        } catch (\Exception $e) {
            if ($alt) {
                return $this->handle($cache, $alt, '', $ignoreErrors);
            }
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }
    protected function handleEsiIncludeTag($attributes)
    {
        $options = array();
        preg_match_all('/(src|onerror|alt)="([^"]*?)"/', $attributes[1], $matches, PREG_SET_ORDER);
        foreach ($matches as $set) {
            $options[$set[1]] = $set[2];
        }
        if (!isset($options['src'])) {
            throw new \RuntimeException('Unable to process an ESI tag without a "src" attribute.');
        }
        return sprintf('<?php echo $this->esi->handle($this, \'%s\', \'%s\', %s) ?>'."\n",
            $options['src'],
            isset($options['alt']) ? $options['alt'] : null,
            isset($options['onerror']) && 'continue' == $options['onerror'] ? 'true' : 'false'
        );
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
namespace Symfony\Component\HttpFoundation
{
class ResponseHeaderBag extends HeaderBag
{
    protected $computedCacheControl = array();
    public function __construct(array $parameters = array())
    {
                        parent::__construct();
        $this->replace($parameters);
    }
    public function replace(array $headers = array())
    {
        parent::replace($headers);
        if (!isset($this->headers['cache-control'])) {
            $this->set('cache-control', '');
        }
    }
    public function set($key, $values, $replace = true)
    {
        parent::set($key, $values, $replace);
                if ('cache-control' === strtr(strtolower($key), '_', '-')) {
            $computed = $this->computeCacheControlValue();
            $this->headers['cache-control'] = array($computed);
            $this->computedCacheControl = $this->parseCacheControl($computed);
        }
    }
    public function remove($key)
    {
        parent::remove($key);
        if ('cache-control' === strtr(strtolower($key), '_', '-')) {
            $this->computedCacheControl = array();
        }
    }
    public function hasCacheControlDirective($key)
    {
        return array_key_exists($key, $this->computedCacheControl);
    }
    public function getCacheControlDirective($key)
    {
        return array_key_exists($key, $this->computedCacheControl) ? $this->computedCacheControl[$key] : null;
    }
    public function clearCookie($name, $path = null, $domain = null)
    {
        $this->setCookie(new Cookie($name, null, time() - 86400, $path, $domain));
    }
    protected function computeCacheControlValue()
    {
        if (!$this->cacheControl && !$this->has('ETag') && !$this->has('Last-Modified') && !$this->has('Expires')) {
            return 'no-cache';
        }
        if (!$this->cacheControl) {
                        return 'private, max-age=0, must-revalidate';
        }
        $header = $this->getCacheControlHeader();
        if (isset($this->cacheControl['public']) || isset($this->cacheControl['private'])) {
            return $header;
        }
                if (!isset($this->cacheControl['s-maxage'])) {
            return $header.', private';
        }
        return $header;
    }
}
}
namespace Symfony\Component\HttpFoundation
{
class Response
{
    public $headers;
    protected $content;
    protected $version;
    protected $statusCode;
    protected $statusText;
    protected $charset = 'UTF-8';
    static public $statusTexts = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    );
    public function __construct($content = '', $status = 200, $headers = array())
    {
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setProtocolVersion('1.0');
        $this->headers = new ResponseHeaderBag($headers);
    }
    public function __toString()
    {
        $content = '';
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'text/html; charset='.$this->charset);
        }
                $content .= sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText)."\n";
                foreach ($this->headers->all() as $name => $values) {
            foreach ($values as $value) {
                $content .= "$name: $value\n";
            }
        }
        $content .= "\n".$this->getContent();
        return $content;
    }
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }
    public function sendHeaders()
    {
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'text/html; charset='.$this->charset);
        }
                header(sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText));
                foreach ($this->headers->all() as $name => $values) {
            foreach ($values as $value) {
                header($name.': '.$value);
            }
        }
                foreach ($this->headers->getCookies() as $cookie) {
            setcookie($cookie->getName(), $cookie->getValue(), $cookie->getExpire(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
        }
    }
    public function sendContent()
    {
        echo $this->content;
    }
    public function send()
    {
        $this->sendHeaders();
        $this->sendContent();
    }
    public function setContent($content)
    {
        $this->content = $content;
    }
    public function getContent()
    {
        return $this->content;
    }
    public function setProtocolVersion($version)
    {
        $this->version = $version;
    }
    public function getProtocolVersion()
    {
        return $this->version;
    }
    public function setStatusCode($code, $text = null)
    {
        $this->statusCode = (int) $code;
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $code));
        }
        $this->statusText = false === $text ? '' : (null === $text ? self::$statusTexts[$this->statusCode] : $text);
    }
    public function getStatusCode()
    {
        return $this->statusCode;
    }
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }
    public function getCharset()
    {
        return $this->charset;
    }
    public function isCacheable()
    {
        if (!in_array($this->statusCode, array(200, 203, 300, 301, 302, 404, 410))) {
            return false;
        }
        if ($this->headers->hasCacheControlDirective('no-store') || $this->headers->getCacheControlDirective('private')) {
            return false;
        }
        return $this->isValidateable() || $this->isFresh();
    }
    public function isFresh()
    {
        return $this->getTtl() > 0;
    }
    public function isValidateable()
    {
        return $this->headers->has('Last-Modified') || $this->headers->has('ETag');
    }
    public function setPrivate()
    {
        $this->headers->removeCacheControlDirective('public');
        $this->headers->addCacheControlDirective('private');
    }
    public function setPublic()
    {
        $this->headers->addCacheControlDirective('public');
        $this->headers->removeCacheControlDirective('private');
    }
    public function mustRevalidate()
    {
        return $this->headers->hasCacheControlDirective('must-revalidate') || $this->headers->has('must-proxy-revalidate');
    }
    public function getDate()
    {
        if (null === $date = $this->headers->getDate('Date')) {
            $date = new \DateTime(null, new \DateTimeZone('UTC'));
            $this->headers->set('Date', $date->format('D, d M Y H:i:s').' GMT');
        }
        return $date;
    }
    public function getAge()
    {
        if ($age = $this->headers->get('Age')) {
            return $age;
        }
        return max(time() - $this->getDate()->format('U'), 0);
    }
    public function expire()
    {
        if ($this->isFresh()) {
            $this->headers->set('Age', $this->getMaxAge());
        }
    }
    public function getExpires()
    {
        return $this->headers->getDate('Expires');
    }
    public function setExpires(\DateTime $date = null)
    {
        if (null === $date) {
            $this->headers->remove('Expires');
        } else {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('UTC'));
            $this->headers->set('Expires', $date->format('D, d M Y H:i:s').' GMT');
        }
    }
    public function getMaxAge()
    {
        if ($age = $this->headers->getCacheControlDirective('s-maxage')) {
            return $age;
        }
        if ($age = $this->headers->getCacheControlDirective('max-age')) {
            return $age;
        }
        if (null !== $this->getExpires()) {
            return $this->getExpires()->format('U') - $this->getDate()->format('U');
        }
        return null;
    }
    public function setMaxAge($value)
    {
        $this->headers->addCacheControlDirective('max-age', $value);
    }
    public function setSharedMaxAge($value)
    {
        $this->headers->addCacheControlDirective('s-maxage', $value);
    }
    public function getTtl()
    {
        if ($maxAge = $this->getMaxAge()) {
            return $maxAge - $this->getAge();
        }
        return null;
    }
    public function setTtl($seconds)
    {
        $this->setSharedMaxAge($this->getAge() + $seconds);
    }
    public function setClientTtl($seconds)
    {
        $this->setMaxAge($this->getAge() + $seconds);
    }
    public function getLastModified()
    {
        return $this->headers->getDate('LastModified');
    }
    public function setLastModified(\DateTime $date = null)
    {
        if (null === $date) {
            $this->headers->remove('Last-Modified');
        } else {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('UTC'));
            $this->headers->set('Last-Modified', $date->format('D, d M Y H:i:s').' GMT');
        }
    }
    public function getEtag()
    {
        return $this->headers->get('ETag');
    }
    public function setEtag($etag = null, $weak = false)
    {
        if (null === $etag) {
            $this->headers->remove('Etag');
        } else {
            if (0 !== strpos($etag, '"')) {
                $etag = '"'.$etag.'"';
            }
            $this->headers->set('ETag', (true === $weak ? 'W/' : '').$etag);
        }
    }
    public function setCache(array $options)
    {
        if ($diff = array_diff(array_keys($options), array('etag', 'last_modified', 'max_age', 's_maxage', 'private', 'public'))) {
            throw new \InvalidArgumentException(sprintf('Response does not support the following options: "%s".', implode('", "', array_keys($diff))));
        }
        if (isset($options['etag'])) {
            $this->setEtag($options['etag']);
        }
        if (isset($options['last_modified'])) {
            $this->setLastModified($options['last_modified']);
        }
        if (isset($options['max_age'])) {
            $this->setMaxAge($options['max_age']);
        }
        if (isset($options['s_maxage'])) {
            $this->setSharedMaxAge($options['s_maxage']);
        }
        if (isset($options['public'])) {
            if ($options['public']) {
                $this->setPublic();
            } else {
                $this->setPrivate();
            }
        }
        if (isset($options['private'])) {
            if ($options['private']) {
                $this->setPrivate();
            } else {
                $this->setPublic();
            }
        }
    }
    public function setNotModified()
    {
        $this->setStatusCode(304);
        $this->setContent(null);
                foreach (array('Allow', 'Content-Encoding', 'Content-Language', 'Content-Length', 'Content-MD5', 'Content-Type', 'Last-Modified') as $header) {
            $this->headers->remove($header);
        }
    }
    public function setRedirect($url, $status = 302)
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }
        $this->setStatusCode($status);
        if (!$this->isRedirect()) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
        }
        $this->headers->set('Location', $url);
        $this->setContent(sprintf('<html><head><meta http-equiv="refresh" content="1;url=%s"/></head></html>', htmlspecialchars($url, ENT_QUOTES)));
    }
    public function hasVary()
    {
        return (Boolean) $this->headers->get('Vary');
    }
    public function getVary()
    {
        if (!$vary = $this->headers->get('Vary')) {
            return array();
        }
        return is_array($vary) ? $vary : preg_split('/[\s,]+/', $vary);
    }
    public function setVary($headers, $replace = true)
    {
        $this->headers->set('Vary', $headers, $replace);
    }
    public function isNotModified(Request $request)
    {
        $lastModified = $request->headers->get('If-Modified-Since');
        $notModified = false;
        if ($etags = $request->getEtags()) {
            $notModified = (in_array($this->getEtag(), $etags) || in_array('*', $etags)) && (!$lastModified || $this->headers->get('Last-Modified') == $lastModified);
        } elseif ($lastModified) {
            $notModified = $lastModified == $this->headers->get('Last-Modified');
        }
        if ($notModified) {
            $this->setNotModified();
        }
        return $notModified;
    }
        public function isInvalid()
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }
    public function isInformational()
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }
    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
    public function isRedirection()
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }
    public function isClientError()
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }
    public function isServerError()
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }
    public function isOk()
    {
        return 200 === $this->statusCode;
    }
    public function isForbidden()
    {
        return 403 === $this->statusCode;
    }
    public function isNotFound()
    {
        return 404 === $this->statusCode;
    }
    public function isRedirect()
    {
        return in_array($this->statusCode, array(301, 302, 303, 307));
    }
    public function isEmpty()
    {
        return in_array($this->statusCode, array(201, 204, 304));
    }
    public function isRedirected($location)
    {
        return $this->isRedirect() && $location == $this->headers->get('Location');
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
