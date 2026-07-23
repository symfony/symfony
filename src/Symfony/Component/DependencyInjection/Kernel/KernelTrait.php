<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Kernel;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\RemoveBuildParametersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\Filesystem\Filesystem;

// Help opcache.preload discover always-needed symbols
class_exists(ConfigCache::class);

/**
 * Manages a dependency-injection container: builds, compiles, caches and loads it.
 *
 * Use this trait to create a DI-powered application (e.g. a console tool).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait KernelTrait
{
    private array $bundleClasses = [];

    public function getCacheDir(): string
    {
        if (null !== $dir = $_SERVER['APP_CACHE_DIR'] ?? null) {
            return $this->getEnvDir($dir);
        }

        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getBuildDir(): string
    {
        if (null !== $dir = $_SERVER['APP_BUILD_DIR'] ?? null) {
            return $this->getEnvDir($dir);
        }

        return $this->getCacheDir();
    }

    public function getShareDir(): ?string
    {
        if (null !== $dir = $_SERVER['APP_SHARE_DIR'] ?? null) {
            if (false === $dir = filter_var($dir, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? $dir) {
                return null;
            }
            if (\is_string($dir)) {
                return $this->getEnvDir($dir);
            }
        }

        return $this->getCacheDir();
    }

    public function getLogDir(): ?string
    {
        if (null !== $dir = $_SERVER['APP_LOG_DIR'] ?? null) {
            if (false === $dir = filter_var($dir, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? $dir) {
                return null;
            }
            if (\is_string($dir)) {
                return $this->getEnvDir($dir);
            }
        }

        return $this->getProjectDir().'/var/log';
    }

    protected function initializeBundles(): void
    {
        $cachePath = $this->getEffectiveBuildDir().'/'.$this->getContainerClass().'.bundles.php';
        if (
            is_file($cachePath)
            && (!$this->debug || is_file($bundlesPath = $this->getBundlesPath()) && filemtime($cachePath) > filemtime($bundlesPath))
        ) {
            $this->bundles = require $cachePath;
            $this->bundleClasses = array_map('get_class', $this->bundles);

            return;
        }

        $this->bundles = [];
        $registered = [];
        foreach ($this->registerBundles() as $bundle) {
            $this->registerBundle($bundle, $registered);
        }

        $this->bundleClasses = array_map('get_class', $this->bundles);
    }

    protected function initializeContainer(): void
    {
        $class = $this->getContainerClass();
        $buildDir = $this->getEffectiveBuildDir();
        $skip = $_SERVER['SYMFONY_DISABLE_RESOURCE_TRACKING'] ?? '';
        $skip = filter_var($skip, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? explode(',', $skip);
        $cache = new ConfigCache($buildDir.'/'.$class.'.php', $this->debug, null, \is_array($skip) && ['*'] !== $skip ? $skip : ($skip ? [] : null));

        $cachePath = $cache->getPath();

        // Silence E_WARNING to ignore "include" failures - don't use "@" to prevent silencing fatal errors
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~\E_WARNING);

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
            is_dir($buildDir) ?: mkdir($buildDir, 0o777, true);

            if ($lock = fopen($cachePath.'.lock', 'w+')) {
                if (!flock($lock, \LOCK_EX | \LOCK_NB, $wouldBlock) && !flock($lock, $wouldBlock ? \LOCK_SH : \LOCK_EX)) {
                    fclose($lock);
                    $lock = null;
                } elseif (!is_file($cachePath) || !\is_object($this->container = include $cachePath)) {
                    $this->container = null;
                } elseif (!$oldContainer || $this->container::class !== $oldContainer->name) {
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
            $previousHandler = set_error_handler(static function ($type, $message, $file, $line) use (&$collectedLogs, &$previousHandler) {
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

        if ($oldContainer && $this->container::class !== $oldContainer->name) {
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
    }

    protected function getContainerClass(): string
    {
        $class = static::class;
        $class = str_contains($class, "@anonymous\0") ? get_parent_class($class).str_replace('.', '_', ContainerBuilder::hash($class)) : $class;
        $class = str_replace('\\', '_', $class).ucfirst($this->environment).($this->debug ? 'Debug' : '').'Container';

        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new \InvalidArgumentException(\sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
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

    protected function buildContainer(): ContainerBuilder
    {
        foreach (['cache' => $this->getCacheDir(), 'build' => $this->getEffectiveBuildDir()] as $name => $dir) {
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0o777, true) && !is_dir($dir)) {
                    throw new \RuntimeException(\sprintf('Unable to create the "%s" directory (%s).', $name, $dir));
                }
            } elseif (!is_writable($dir)) {
                throw new \RuntimeException(\sprintf('Unable to write in the "%s" directory (%s).', $name, $dir));
            }
        }

        $container = $this->getContainerBuilder();
        $container->addObjectResource($this);
        $container->fileExists($this->getBundlesPath());
        $this->prepareContainer($container);
        $this->registerContainerConfiguration($this->getContainerLoader($container));

        return $container;
    }

    /**
     * Prepares the ContainerBuilder before it is compiled.
     */
    protected function prepareContainer(ContainerBuilder $container): void
    {
        foreach ($this->bundles as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $container->registerExtension($extension);
            }
            if ($this->debug) {
                $container->addObjectResource($bundle);
            }
            if ($bundle instanceof CompilerPassInterface) {
                $container->addCompilerPass($bundle, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10000);
            }
        }

        foreach ($this->bundles as $bundle) {
            $bundle->build($container);
        }

        $this->build($container);

        $extensions = [];
        foreach ($container->getExtensions() as $extension) {
            $extensions[] = $extension->getAlias();
        }

        // ensure registered extensions are implicitly loaded
        $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass($extensions));
    }

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

    protected function dumpContainer(ConfigCache $cache, ContainerBuilder $container, string $class, string $baseClass): void
    {
        $dumper = new PhpDumper($container);

        $buildParameters = [];
        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof RemoveBuildParametersPass) {
                $buildParameters = array_merge($buildParameters, $pass->getRemovedParameters());
            }
        }

        if (null === $buildTime = filter_var($_SERVER['SOURCE_DATE_EPOCH'] ?? null, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE)) {
            $buildTime = time();
        }

        $content = $dumper->dump([
            'class' => $class,
            'base_class' => $baseClass,
            'file' => $cache->getPath(),
            'as_files' => true,
            'debug' => $this->debug,
            'inline_factories' => $buildParameters['.container.dumper.inline_factories'] ?? false,
            'inline_class_loader' => $buildParameters['.container.dumper.inline_class_loader'] ?? $this->debug,
            'build_time' => $container->hasParameter('kernel.container_build_time') ? $container->getParameter('kernel.container_build_time') : $buildTime,
            'preload_classes' => $this->bundleClasses,
        ]);

        $rootCode = array_pop($content);
        $dir = \dirname($cache->getPath()).'/';

        $fs = new Filesystem();

        foreach ($content as $file => $code) {
            $fs->dumpFile($dir.$file, $code);
            @chmod($dir.$file, 0o666 & ~umask());
        }
        $legacyFile = \dirname($dir.key($content)).'.legacy';
        if (is_file($legacyFile)) {
            @unlink($legacyFile);
        }

        $cache->write($rootCode, $container->getResources());

        // Dump resolved bundle list so initializeBundles() can skip reflection on next boot
        $code = "<?php\n\nreturn [\n";
        foreach ($this->bundleClasses as $name => $bundleClass) {
            $code .= \sprintf("    %s => new \\%s(),\n", var_export($name, true), $bundleClass);
        }
        $code .= "];\n";
        $fs->dumpFile($this->getEffectiveBuildDir().'/'.$class.'.bundles.php', $code);
    }

    protected function getContainerLoader(ContainerInterface $container): DelegatingLoader
    {
        $env = $this->getEnvironment();
        $locator = new FileLocator($this);
        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator, $env),
            new IniFileLoader($container, $locator, $env),
            new PhpFileLoader($container, $locator, $env),
            new GlobFileLoader($container, $locator, $env),
            new DirectoryLoader($container, $locator, $env),
            new ClosureLoader($container, $env),
        ]);

        return new DelegatingLoader($resolver);
    }

    /**
     * Returns the allowed environment names.
     *
     * Override this to restrict the environments that are allowed to run.
     *
     * @return string[]
     */
    private function getAllowedEnvs(): array
    {
        return [];
    }

    private function getConfigDir(): string
    {
        return $this->getProjectDir().'/config';
    }

    private function getBundlesPath(): string
    {
        return $this->getConfigDir().'/bundles.php';
    }

    /**
     * Configures the container.
     *
     * Define a configureContainer() method on your kernel to customize the container
     * configuration. The method signature is flexible: you may declare only the
     * parameters you need (ContainerConfigurator, LoaderInterface, ContainerBuilder).
     *
     * You can register extensions:
     *
     *     $container->extension('framework', [
     *         'secret' => '%secret%'
     *     ]);
     *
     * Or services:
     *
     *     $container->services()->set('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     *     $container->parameters()->set('halloween', 'lot of fun');
     */
    private function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = preg_replace('{/config$}', '/{config}', $this->getConfigDir());

        $container->import($configDir.'/{packages}/*.{php,yaml}');
        $container->import($configDir.'/{packages}/'.$this->environment.'/*.{php,yaml}');

        if (is_file($this->getConfigDir().'/services.yaml')) {
            $container->import($configDir.'/services.yaml');
            $container->import($configDir.'/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import($configDir.'/{services}.php');
            $container->import($configDir.'/{services}_'.$this->environment.'.php');
        }
    }

    private function getBundlesDefinition(): array
    {
        $bundlesPath = $this->getBundlesPath();
        $bundles = is_file($bundlesPath) ? require $bundlesPath : [];

        $resolved = [];
        foreach ($bundles as $class => $envs) {
            $this->resolveRequiredBundles($class, $envs, $bundles, $resolved);
        }

        return $resolved;
    }

    private function registerBundles(): iterable
    {
        if (!$this->getBundlesDefinition()) {
            return;
        }

        foreach ($this->getBundlesDefinition() as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    /**
     * Returns the effective build directory, which may differ from getBuildDir()
     * during cache warmup.
     */
    private function getEffectiveBuildDir(): string
    {
        return $this->getBuildDir();
    }

    private function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) use ($loader) {
            $kernelClass = str_contains(static::class, "@anonymous\0") ? get_parent_class(static::class) : static::class;

            if (!$container->hasDefinition('kernel')) {
                $container->register('kernel', $kernelClass)
                    ->setSynthetic(true)
                    ->setPublic(true)
                ;
            }

            $configureContainer = new \ReflectionMethod($this, 'configureContainer');
            $configuratorClass = $configureContainer->getNumberOfParameters() > 0
                && ($type = $configureContainer->getParameters()[0]->getType()) instanceof \ReflectionNamedType
                && !$type->isBuiltin()
                    ? $type->getName()
                    : null;

            if ($configuratorClass && !is_a(ContainerConfigurator::class, $configuratorClass, true)) {
                $configureContainer->getClosure($this)($container, $loader);

                return;
            }

            $file = (new \ReflectionObject($this))->getFileName();
            $kernelLoader = new PhpFileLoader($container, new FileLocator($this), $this->getEnvironment());
            $kernelLoader->setResolver($loader->getResolver());
            $kernelLoader->setCurrentDir(\dirname($file));
            $instanceof = &\Closure::bind(fn &() => $this->instanceof, $kernelLoader, $kernelLoader)();

            $valuePreProcessor = AbstractConfigurator::$valuePreProcessor;
            AbstractConfigurator::$valuePreProcessor = fn ($value) => $this === $value ? new Reference('kernel') : $value;

            try {
                $configurator = new ContainerConfigurator($container, $kernelLoader, $instanceof, $file, $file, $this->getEnvironment());
                $configureContainer->getClosure($this)($configurator, $loader, $container);
            } finally {
                $instanceof = [];
                $kernelLoader->registerAliasesForSinglyImplementedInterfaces();
                AbstractConfigurator::$valuePreProcessor = $valuePreProcessor;
            }

            $container->setAlias($kernelClass, 'kernel')->setPublic(true);
        });
    }

    /**
     * @return array<string, array|bool|string|int|float|\UnitEnum|null>
     */
    private function getKernelParameters(): array
    {
        $bundles = [];
        $bundlesMetadata = [];

        foreach ($this->bundles as $name => $bundle) {
            $bundles[$name] = $bundle::class;
            $bundlesMetadata[$name] = [
                'path' => $bundle->getPath(),
            ];
        }

        if (!$knownEnvs = array_flip($this->getAllowedEnvs())) {
            foreach ($this->getBundlesDefinition() as $envs) {
                $knownEnvs += $envs;
            }
            $knownEnvs += [$this->environment => true];
            unset($knownEnvs['all']);
        } elseif (!isset($knownEnvs[$this->environment])) {
            throw new \InvalidArgumentException(\sprintf('The environment "%s" is not registered as allowed by "%s::getAllowedEnvs()".', $this->environment, static::class));
        }

        return [
            'kernel.project_dir' => realpath($this->getProjectDir()) ?: $this->getProjectDir(),
            'kernel.environment' => $this->environment,
            'kernel.runtime_environment' => '%env(default:kernel.environment:APP_RUNTIME_ENV)%',
            'kernel.runtime_mode' => '%env(query_string:default:container.runtime_mode:APP_RUNTIME_MODE)%',
            'kernel.runtime_mode.web' => '%env(bool:default::key:web:default:kernel.runtime_mode:)%',
            'kernel.runtime_mode.cli' => '%env(not:default:kernel.runtime_mode.web:)%',
            'kernel.runtime_mode.worker' => '%env(int:default::key:worker:default:kernel.runtime_mode:)%',
            'kernel.debug' => $this->debug,
            'kernel.build_dir' => realpath($dir = $this->getEffectiveBuildDir()) ?: $dir,
            'kernel.cache_dir' => realpath($dir = ($this->getCacheDir() === $this->getBuildDir() ? $this->getEffectiveBuildDir() : $this->getCacheDir())) ?: $dir,
            'kernel.bundles' => $bundles,
            'kernel.bundles_metadata' => $bundlesMetadata,
            'kernel.container_class' => $this->getContainerClass(),
            '.kernel.config_dir' => $this->getConfigDir(),
            '.kernel.bundles_definition' => $this->getBundlesDefinition(),
            '.container.known_envs' => array_keys($knownEnvs),
        ] + (null !== ($dir = $this->getLogDir()) ? [
            'kernel.logs_dir' => realpath($dir) ?: $dir,
        ] : []) + (null !== ($dir = $this->getShareDir()) ? [
            'kernel.share_dir' => realpath($dir) ?: $dir,
        ] : []);
    }

    private function getEnvDir(string $dir): string
    {
        if ('' !== $dir && \in_array($dir[0], ['/', '\\'], true)) {
            return $dir.'/'.$this->environment;
        }
        if ('\\' === \DIRECTORY_SEPARATOR && ':' === ($dir[1] ?? '') && 65 <= \ord($dir[0]) && \ord($dir[0]) <= 122 && !\in_array($dir[0], ['[', ']', '^', '_', '`'], true)) {
            return $dir.'/'.$this->environment;
        }

        return $this->getProjectDir().'/'.$dir.'/'.$this->environment;
    }

    private function resolveRequiredBundles(string $class, array $envs, array $bundles, array &$resolved, array &$visiting = []): void
    {
        if (isset($resolved[$class]) || isset($visiting[$class])) {
            return;
        }

        if (!class_exists($class)) {
            $resolved[$class] = $envs;

            return;
        }

        $visiting[$class] = true;

        foreach ((new \ReflectionClass($class))->getAttributes(RequiredBundle::class) as $attribute) {
            $required = $attribute->newInstance();
            if ($required->ignoreOnInvalid && !class_exists($required->class)) {
                continue;
            }
            if (!isset($bundles[$required->class])) {
                $this->resolveRequiredBundles($required->class, $envs, $bundles, $resolved, $visiting);
            }
        }

        $resolved[$class] = $envs;
    }

    private function registerBundle(BundleInterface $bundle, array &$registered): void
    {
        $class = $bundle::class;

        if (isset($registered[$class])) {
            return;
        }
        $registered[$class] = true;

        foreach ((new \ReflectionClass($class))->getAttributes(RequiredBundle::class) as $attribute) {
            $required = $attribute->newInstance();
            if (isset($registered[$required->class])) {
                continue;
            }
            if ($required->ignoreOnInvalid && !class_exists($required->class)) {
                continue;
            }
            $this->registerBundle(new $required->class(), $registered);
        }

        $name = $bundle->getName();
        if (isset($this->bundles[$name])) {
            throw new \LogicException(\sprintf('Trying to register two bundles with the same name "%s".', $name));
        }
        $this->bundles[$name] = $bundle;
    }
}
