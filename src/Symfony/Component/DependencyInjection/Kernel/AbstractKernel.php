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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for DI-powered applications.
 *
 * Provides properties, lifecycle orchestration and public API.
 * Pair with KernelTrait for the container building implementation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractKernel implements KernelInterface
{
    /** @var array<string, BundleInterface> */
    protected array $bundles = [];
    protected ?ContainerInterface $container = null;
    protected bool $booted = false;
    protected ?float $startTime = null;

    /** @var array<string, bool> */
    protected static array $freshCache = [];

    private string $projectDir;

    public function __construct(
        protected string $environment,
        protected bool $debug,
    ) {
        if (!$environment) {
            throw new \InvalidArgumentException(\sprintf('Invalid environment provided to "%s": the environment cannot be empty.', get_debug_type($this)));
        }
    }

    public function __clone()
    {
        $this->booted = false;
        $this->container = null;
        $this->startTime = null;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if ($this->debug) {
            $this->startTime = microtime(true);
        }

        if ($this->debug && !isset($_ENV['SHELL_VERBOSITY']) && !isset($_SERVER['SHELL_VERBOSITY'])) {
            if (\function_exists('putenv')) {
                putenv('SHELL_VERBOSITY=3');
            }
            $_ENV['SHELL_VERBOSITY'] = 3;
            $_SERVER['SHELL_VERBOSITY'] = 3;
        }

        $this->initializeBundles();

        if (!$this->container) {
            $this->initializeContainer();
        }

        foreach ($this->bundles as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->booted = true;
    }

    public function shutdown(): void
    {
        if (!$this->booted) {
            return;
        }

        $this->booted = false;

        foreach ($this->bundles as $bundle) {
            $bundle->shutdown();
            $bundle->setContainer(null);
        }

        $this->container = null;
    }

    /**
     * @return array<string, BundleInterface>
     */
    public function getBundles(): array
    {
        return $this->bundles;
    }

    public function getBundle(string $name): BundleInterface
    {
        if (!isset($this->bundles[$name])) {
            throw new \InvalidArgumentException(\sprintf('Bundle "%s" does not exist or it is not enabled. Maybe you forgot to add it in the "registerBundles()" method of your "%s.php" file?', $name, get_debug_type($this)));
        }

        return $this->bundles[$name];
    }

    public function locateResource(string $name): string
    {
        if ('@' !== $name[0]) {
            throw new \InvalidArgumentException(\sprintf('A resource name must start with @ ("%s" given).', $name));
        }

        if (str_contains($name, '..')) {
            throw new \RuntimeException(\sprintf('File name "%s" contains invalid characters (..).', $name));
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

        throw new \InvalidArgumentException(\sprintf('Unable to find file "%s".', $name));
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
                throw new \LogicException(\sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
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

    public function getStartTime(): float
    {
        return $this->debug && null !== $this->startTime ? $this->startTime : -\INF;
    }

    public function __serialize(): array
    {
        return [
            'environment' => $this->environment,
            'debug' => $this->debug,
        ];
    }

    public function __unserialize(array $data): void
    {
        $environment = $data['environment'] ?? $data["\0*\0environment"];
        $debug = $data['debug'] ?? $data["\0*\0debug"];

        if (\is_object($environment) || \is_object($debug)) {
            throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
        }

        $this->environment = $environment;
        $this->debug = $debug;

        $this->__construct($environment, $debug);
    }

    abstract protected function initializeBundles(): void;

    /**
     * Initializes the service container.
     *
     * The built version of the service container is used when fresh, otherwise the
     * container is built.
     */
    abstract protected function initializeContainer(): void;

    /**
     * initializeContainer() should call this method to allow registering compiler passes and manipulating the container during the building process.
     */
    protected function build(ContainerBuilder $container): void
    {
    }
}
