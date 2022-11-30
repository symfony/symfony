<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Dumper\Preloader;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\AddAnnotatedClassesToCachePass;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;

// Help opcache.preload discover always-needed symbols
class_exists(ConfigCache::class);

/**
 * The Kernel is the heart of the Symfony system.
 *
 * It manages an environment made of bundles.
 *
 * Environment names must always start with a letter and
 * they must only contain letters and numbers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Kernel implements KernelInterface, RebootableInterface, TerminableInterface
{
    /**
     * @var array<string, BundleInterface>
     */
    protected $bundles = [];

    protected $container;
    protected $environment;
    protected $debug;
    protected $booted = false;
    protected $startTime;

    private string $projectDir;
    private ?string $warmupDir = null;
    private int $requestStackSize = 0;
    private bool $resetServices = false;

    /**
     * @var array<string, bool>
     */
    private static array $freshCache = [];

    public const VERSION = '6.2.0';
    public const VERSION_ID = 60200;
    public const MAJOR_VERSION = 6;
    public const MINOR_VERSION = 2;
    public const RELEASE_VERSION = 0;
    public const EXTRA_VERSION = '';

    public const END_OF_MAINTENANCE = '07/2023';
    public const END_OF_LIFE = '07/2023';

    public function __construct(string $environment, bool $debug)
    {
        if (!$this->environment = $environment) {
            throw new \InvalidArgumentException(sprintf('Invalid environment provided to "%s": the environment cannot be empty.', get_debug_type($this)));
        }

        $this->debug = $debug;
    }

    public function __clone()
    {
        $this->booted = false;
        $this->container = null;
        $this->requestStackSize = 0;
        $this->resetServices = false;
    }

    public function boot()
    {
        if (true === $this->booted) {
            if (!$this->requestStackSize && $this->resetServices) {
                if ($this->container->has('services_resetter')) {
                    $this->container->get('services_resetter')->reset();
                }
                $this->resetServices = false;
                if ($this->debug) {
                    $this->startTime = microtime(true);
                }
            }

            return;
        }

        if (null === $this->container) {
            $this->preBoot();
        }

        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->booted = true;
    }

    public function reboot(?string $warmupDir)
    {
        $this->shutdown();
        $this->warmupDir = $warmupDir;
        $this->boot();
    }

    public function terminate(Request $request, Response $response)
    {
        if (false === $this->booted) {
            return;
        }

        if ($this->getHttpKernel() instanceof TerminableInterface) {
            $this->getHttpKernel()->terminate($request, $response);
        }
    }

    public function shutdown()
    {
        if (false === $this->booted) {
            return;
        }

        $this->booted = false;

        foreach ($this->getBundles() as $bundle) {
            $bundle->shutdown();
            $bundle->setContainer(null);
        }

        $this->container = null;
        $this->requestStackSize = 0;
        $this->resetServices = false;
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if (!$this->booted) {
            $container = $this->container ?? $this->preBoot();

            if ($container->has('http_cache')) {
                return $container->get('http_cache')->handle($request, $type, $catch);
            }
        }

        $this->boot();
        ++$this->requestStackSize;
        $this->resetServices = true;

        try {
            return $this->getHttpKernel()->handle($request, $type, $catch);
        } finally {
            --$this->requestStackSize;
        }
    }

    /**
     * Gets an HTTP kernel from the container.
     */
    protected function getHttpKernel(): HttpKernelInterface
    {
        return $this->container->get('http_kernel');
    }

    public function getBundles(): array
    {
        return $this->bundles;
    }

    public function getBundle(string $name): BundleInterface
    {
        if (!isset($this->bundles[$name])) {
            throw new \InvalidArgumentException(sprintf('Bundle "%s" does not exist or it is not enabled. Maybe you forgot to add it in the "registerBundles()" method of your "%s.php" file?', $name, get_debug_type($this)));
        }

        return $this->bundles[$name];
    }

    public function locateResource(string $name): string
    {
        if ('@' !== $name[0]) {
            throw new \InvalidArgumentException(sprintf('A resource name must start with @ ("%s" given).', $name));
        }

        if (str_contains($name, '..')) {
            throw new \RuntimeException(sprintf('File name "%s" contains invalid characters (..).', $name));
        }

        $bundleName = substr($name, 1);
        $path = '';
        if (str_contains($bundleName, '/')) {
            [$bundleName, $path] = explode('/', $bundleName, 2);
        }

        $bundle = $this->getBundle($bundleName);
        if (file_exists($file = $bundle->getPath().'/'.$path)) {
            return $file;
        }

        throw new \InvalidArgumentException(sprintf('Unable to find file "%s".', $name));
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     */
    public function getProjectDir(): string
    {
        if (!isset($this->projectDir)) {
            $r = new \ReflectionObject($this);

            if (!is_file($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!is_file($dir.'/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    public function getContainer(): ContainerInterface
    {
        if (!$this->container) {
            throw new \LogicException('Cannot retrieve the container from a non-booted kernel.');
        }

        return $this->container;
    }

    /**
     * @internal
     */
    public function setAnnotatedClassCache(array $annotatedClasses)
    {
        file_put_contents(($this->warmupDir ?: $this->getBuildDir()).'/annotations.map', sprintf('<?php return %s;', var_export($annotatedClasses, true)));
    }

    public function getStartTime(): float
    {
        return $this->debug && null !== $this->startTime ? $this->startTime : -\INF;
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getBuildDir(): string
    {
        // Returns $this->getCacheDir() for backward compatibility
        return $this->getCacheDir();
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    public function getCharset(): string
    {
        return 'UTF-8';
    }

    /**
     * Gets the patterns defining the classes to parse and cache for annotations.
     */
    public function getAnnotatedClassesToCompile(): array
    {
        return [];
    }

    /**
     * Initializes bundles.
     *
     * @throws \LogicException if two bundles share a common name
     */
    protected function initializeBundles()
    {
        // init bundles
        $this->bundles = [];
        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            if (isset($this->bundles[$name])) {
                throw new \LogicException(sprintf('Trying to register two bundles with the same name "%s".', $name));
            }
            $this->bundles[$name] = $bundle;
        }
    }

    /**
     * The extension point similar to the Bundle::build() method.
     *
     * Use this method to register compiler passes and manipulate the container during the building process.
     */
    protected function build(ContainerBuilder $container)
    {
    }

    /**
     * Gets the container class.
     *
     * @throws \InvalidArgumentException If the generated classname is invalid
     */
    protected function getContainerClass(): string
    {
        $class = static::class;
        $class = str_contains($class, "@anonymous\0") ? get_parent_class($class).str_replace('.', '_', ContainerBuilder::hash($class)) : $class;
        $class = str_replace('\\', '_', $class).ucfirst($this->environment).($this->debug ? 'Debug' : '').'Container';

        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new \InvalidArgumentException(sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
        }

        return $class;
    }

    /**
     * Gets the container's base class.
     *
     * All names except Container must be fully qualified.
     */
    protected function getContainerBaseClass(): string
    {
        return 'Container';
    }

    /**
     * Initializes the service container.
     *
     * The built version of the service container is used when fresh, otherwise the
     * container is built.
     */
    protected function initializeContainer()
    {
        $class = $this->getContainerClass();
        $buildDir = $this->warmupDir ?: $this->getBuildDir();
        $cache = new ConfigCache($buildDir.'/'.$class.'.php', $this->debug);
        $cachePath = $cache->getPath();

        // Silence E_WARNING to ignore "include" failures - don't use "@" to prevent silencing fatal errors
        $errorLevel = error_reporting(\E_ALL ^ \E_WARNING);

        try {
            if (is_file($cachePath) && \is_object($this->container = include $cachePath)
                && (!$this->debug || (self::$freshCache[$cachePath] ?? $cache->isFresh()))
            ) {
                self::$freshCache[$cachePath] = true;
                $this->container->set('kernel', $this);
                error_reporting($errorLevel);

                return;
            }
        } catch (\Throwable $e) {
        }

        $oldContainer = \is_object($this->container) ? new \ReflectionClass($this->container) : $this->container = null;

        try {
            is_dir($buildDir) ?: mkdir($buildDir, 0777, true);

            if ($lock = fopen($cachePath.'.lock', 'w')) {
                if (!flock($lock, \LOCK_EX | \LOCK_NB, $wouldBlock) && !flock($lock, $wouldBlock ? \LOCK_SH : \LOCK_EX)) {
                    fclose($lock);
                    $lock = null;
                } elseif (!is_file($cachePath) || !\is_object($this->container = include $cachePath)) {
                    $this->container = null;
                } elseif (!$oldContainer || \get_class($this->container) !== $oldContainer->name) {
                    flock($lock, \LOCK_UN);
                    fclose($lock);
                    $this->container->set('kernel', $this);

                    return;
                }
            }
        } catch (\Throwable $e) {
        } finally {
            error_reporting($errorLevel);
        }

        if ($collectDeprecations = $this->debug && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
            $collectedLogs = [];
            $previousHandler = set_error_handler(function ($type, $message, $file, $line) use (&$collectedLogs, &$previousHandler) {
                if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type) {
                    return $previousHandler ? $previousHandler($type, $message, $file, $line) : false;
                }

                if (isset($collectedLogs[$message])) {
                    ++$collectedLogs[$message]['count'];

                    return null;
                }

                $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 5);
                // Clean the trace by removing first frames added by the error handler itself.
                for ($i = 0; isset($backtrace[$i]); ++$i) {
                    if (isset($backtrace[$i]['file'], $backtrace[$i]['line']) && $backtrace[$i]['line'] === $line && $backtrace[$i]['file'] === $file) {
                        $backtrace = \array_slice($backtrace, 1 + $i);
                        break;
                    }
                }
                for ($i = 0; isset($backtrace[$i]); ++$i) {
                    if (!isset($backtrace[$i]['file'], $backtrace[$i]['line'], $backtrace[$i]['function'])) {
                        continue;
                    }
                    if (!isset($backtrace[$i]['class']) && 'trigger_deprecation' === $backtrace[$i]['function']) {
                        $file = $backtrace[$i]['file'];
                        $line = $backtrace[$i]['line'];
                        $backtrace = \array_slice($backtrace, 1 + $i);
                        break;
                    }
                }

                // Remove frames added by DebugClassLoader.
                for ($i = \count($backtrace) - 2; 0 < $i; --$i) {
                    if (DebugClassLoader::class === ($backtrace[$i]['class'] ?? null)) {
                        $backtrace = [$backtrace[$i + 1]];
                        break;
                    }
                }

                $collectedLogs[$message] = [
                    'type' => $type,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'trace' => [$backtrace[0]],
                    'count' => 1,
                ];

                return null;
            });
        }

        try {
            $container = null;
            $container = $this->buildContainer();
            $container->compile();
        } finally {
            if ($collectDeprecations) {
                restore_error_handler();

                @file_put_contents($buildDir.'/'.$class.'Deprecations.log', serialize(array_values($collectedLogs)));
                @file_put_contents($buildDir.'/'.$class.'Compiler.log', null !== $container ? implode("\n", $container->getCompiler()->getLog()) : '');
            }
        }

        $this->dumpContainer($cache, $container, $class, $this->getContainerBaseClass());

        if ($lock) {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }

        $this->container = require $cachePath;
        $this->container->set('kernel', $this);

        if ($oldContainer && \get_class($this->container) !== $oldContainer->name) {
            // Because concurrent requests might still be using them,
            // old container files are not removed immediately,
            // but on a next dump of the container.
            static $legacyContainers = [];
            $oldContainerDir = \dirname($oldContainer->getFileName());
            $legacyContainers[$oldContainerDir.'.legacy'] = true;
            foreach (glob(\dirname($oldContainerDir).\DIRECTORY_SEPARATOR.'*.legacy', \GLOB_NOSORT) as $legacyContainer) {
                if (!isset($legacyContainers[$legacyContainer]) && @unlink($legacyContainer)) {
                    (new Filesystem())->remove(substr($legacyContainer, 0, -7));
                }
            }

            touch($oldContainerDir.'.legacy');
        }

        $preload = $this instanceof WarmableInterface ? (array) $this->warmUp($this->container->getParameter('kernel.cache_dir')) : [];

        if ($this->container->has('cache_warmer')) {
            $preload = array_merge($preload, (array) $this->container->get('cache_warmer')->warmUp($this->container->getParameter('kernel.cache_dir')));
        }

        if ($preload && method_exists(Preloader::class, 'append') && file_exists($preloadFile = $buildDir.'/'.$class.'.preload.php')) {
            Preloader::append($preloadFile, $preload);
        }
    }

    /**
     * Returns the kernel parameters.
     */
    protected function getKernelParameters(): array
    {
        $bundles = [];
        $bundlesMetadata = [];

        foreach ($this->bundles as $name => $bundle) {
            $bundles[$name] = $bundle::class;
            $bundlesMetadata[$name] = [
                'path' => $bundle->getPath(),
                'namespace' => $bundle->getNamespace(),
            ];
        }

        return [
            'kernel.project_dir' => realpath($this->getProjectDir()) ?: $this->getProjectDir(),
            'kernel.environment' => $this->environment,
            'kernel.runtime_environment' => '%env(default:kernel.environment:APP_RUNTIME_ENV)%',
            'kernel.debug' => $this->debug,
            'kernel.build_dir' => realpath($buildDir = $this->warmupDir ?: $this->getBuildDir()) ?: $buildDir,
            'kernel.cache_dir' => realpath($cacheDir = ($this->getCacheDir() === $this->getBuildDir() ? ($this->warmupDir ?: $this->getCacheDir()) : $this->getCacheDir())) ?: $cacheDir,
            'kernel.logs_dir' => realpath($this->getLogDir()) ?: $this->getLogDir(),
            'kernel.bundles' => $bundles,
            'kernel.bundles_metadata' => $bundlesMetadata,
            'kernel.charset' => $this->getCharset(),
            'kernel.container_class' => $this->getContainerClass(),
        ];
    }

    /**
     * Builds the service container.
     *
     * @throws \RuntimeException
     */
    protected function buildContainer(): ContainerBuilder
    {
        foreach (['cache' => $this->getCacheDir(), 'build' => $this->warmupDir ?: $this->getBuildDir(), 'logs' => $this->getLogDir()] as $name => $dir) {
            if (!is_dir($dir)) {
                if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new \RuntimeException(sprintf('Unable to create the "%s" directory (%s).', $name, $dir));
                }
            } elseif (!is_writable($dir)) {
                throw new \RuntimeException(sprintf('Unable to write in the "%s" directory (%s).', $name, $dir));
            }
        }

        $container = $this->getContainerBuilder();
        $container->addObjectResource($this);
        $this->prepareContainer($container);
        $this->registerContainerConfiguration($this->getContainerLoader($container));

        $container->addCompilerPass(new AddAnnotatedClassesToCachePass($this));

        return $container;
    }

    /**
     * Prepares the ContainerBuilder before it is compiled.
     */
    protected function prepareContainer(ContainerBuilder $container)
    {
        $extensions = [];
        foreach ($this->bundles as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $container->registerExtension($extension);
            }

            if ($this->debug) {
                $container->addObjectResource($bundle);
            }
        }

        foreach ($this->bundles as $bundle) {
            $bundle->build($container);
        }

        $this->build($container);

        foreach ($container->getExtensions() as $extension) {
            $extensions[] = $extension->getAlias();
        }

        // ensure these extensions are implicitly loaded
        $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass($extensions));
    }

    /**
     * Gets a new ContainerBuilder instance used to build the service container.
     */
    protected function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->add($this->getKernelParameters());

        if ($this instanceof ExtensionInterface) {
            $container->registerExtension($this);
        }
        if ($this instanceof CompilerPassInterface) {
            $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10000);
        }

        return $container;
    }

    /**
     * Dumps the service container to PHP code in the cache.
     *
     * @param string $class     The name of the class to generate
     * @param string $baseClass The name of the container's base class
     */
    protected function dumpContainer(ConfigCache $cache, ContainerBuilder $container, string $class, string $baseClass)
    {
        // cache the container
        $dumper = new PhpDumper($container);

        $content = $dumper->dump([
            'class' => $class,
            'base_class' => $baseClass,
            'file' => $cache->getPath(),
            'as_files' => true,
            'debug' => $this->debug,
            'build_time' => $container->hasParameter('kernel.container_build_time') ? $container->getParameter('kernel.container_build_time') : time(),
            'preload_classes' => array_map('get_class', $this->bundles),
        ]);

        $rootCode = array_pop($content);
        $dir = \dirname($cache->getPath()).'/';
        $fs = new Filesystem();

        foreach ($content as $file => $code) {
            $fs->dumpFile($dir.$file, $code);
            @chmod($dir.$file, 0666 & ~umask());
        }
        $legacyFile = \dirname($dir.key($content)).'.legacy';
        if (is_file($legacyFile)) {
            @unlink($legacyFile);
        }

        $cache->write($rootCode, $container->getResources());
    }

    /**
     * Returns a loader for the container.
     */
    protected function getContainerLoader(ContainerInterface $container): DelegatingLoader
    {
        $env = $this->getEnvironment();
        $locator = new FileLocator($this);
        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator, $env),
            new YamlFileLoader($container, $locator, $env),
            new IniFileLoader($container, $locator, $env),
            new PhpFileLoader($container, $locator, $env, class_exists(ConfigBuilderGenerator::class) ? new ConfigBuilderGenerator($this->getBuildDir()) : null),
            new GlobFileLoader($container, $locator, $env),
            new DirectoryLoader($container, $locator, $env),
            new ClosureLoader($container, $env),
        ]);

        return new DelegatingLoader($resolver);
    }

    private function preBoot(): ContainerInterface
    {
        if ($this->debug) {
            $this->startTime = microtime(true);
        }
        if ($this->debug && !isset($_ENV['SHELL_VERBOSITY']) && !isset($_SERVER['SHELL_VERBOSITY'])) {
            putenv('SHELL_VERBOSITY=3');
            $_ENV['SHELL_VERBOSITY'] = 3;
            $_SERVER['SHELL_VERBOSITY'] = 3;
        }

        $this->initializeBundles();
        $this->initializeContainer();

        $container = $this->container;

        if ($container->hasParameter('kernel.trusted_hosts') && $trustedHosts = $container->getParameter('kernel.trusted_hosts')) {
            Request::setTrustedHosts($trustedHosts);
        }

        if ($container->hasParameter('kernel.trusted_proxies') && $container->hasParameter('kernel.trusted_headers') && $trustedProxies = $container->getParameter('kernel.trusted_proxies')) {
            Request::setTrustedProxies(\is_array($trustedProxies) ? $trustedProxies : array_map('trim', explode(',', $trustedProxies)), $container->getParameter('kernel.trusted_headers'));
        }

        return $container;
    }

    /**
     * Removes comments from a PHP source string.
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     */
    public static function stripComments(string $source): string
    {
        if (!\function_exists('token_get_all')) {
            return $source;
        }

        $rawChunk = '';
        $output = '';
        $tokens = token_get_all($source);
        $ignoreSpace = false;
        for ($i = 0; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];
            if (!isset($token[1]) || 'b"' === $token) {
                $rawChunk .= $token;
            } elseif (\T_START_HEREDOC === $token[0]) {
                $output .= $rawChunk.$token[1];
                do {
                    $token = $tokens[++$i];
                    $output .= isset($token[1]) && 'b"' !== $token ? $token[1] : $token;
                } while (\T_END_HEREDOC !== $token[0]);
                $rawChunk = '';
            } elseif (\T_WHITESPACE === $token[0]) {
                if ($ignoreSpace) {
                    $ignoreSpace = false;

                    continue;
                }

                // replace multiple new lines with a single newline
                $rawChunk .= preg_replace(['/\n{2,}/S'], "\n", $token[1]);
            } elseif (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT])) {
                if (!\in_array($rawChunk[\strlen($rawChunk) - 1], [' ', "\n", "\r", "\t"], true)) {
                    $rawChunk .= ' ';
                }
                $ignoreSpace = true;
            } else {
                $rawChunk .= $token[1];

                // The PHP-open tag already has a new-line
                if (\T_OPEN_TAG === $token[0]) {
                    $ignoreSpace = true;
                } else {
                    $ignoreSpace = false;
                }
            }
        }

        $output .= $rawChunk;

        unset($tokens, $rawChunk);
        gc_mem_caches();

        return $output;
    }

    public function __sleep(): array
    {
        return ['environment', 'debug'];
    }

    public function __wakeup()
    {
        if (\is_object($this->environment) || \is_object($this->debug)) {
            throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
        }

        $this->__construct($this->environment, $this->debug);
    }
}
