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

use Symfony\Component\DependencyInjection\Dumper\Preloader;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleAdapter;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

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
abstract class Kernel extends AbstractKernel implements KernelInterface, RebootableInterface, TerminableInterface
{
    use KernelTrait {
        registerBundles as public;
        registerContainerConfiguration as public;
        initializeBundles as protected doInitializeBundles;
        initializeContainer as protected doInitializeContainer;
        getKernelParameters as private doGetKernelParameters;
    }

    private ?string $warmupDir = null;
    private int $requestStackSize = 0;
    private bool $resetServices = false;
    private bool $handlingHttpCache = false;

    public const VERSION = '8.1.0-DEV';
    public const VERSION_ID = 80100;
    public const MAJOR_VERSION = 8;
    public const MINOR_VERSION = 1;
    public const RELEASE_VERSION = 0;
    public const EXTRA_VERSION = 'DEV';

    public const END_OF_MAINTENANCE = '01/2027';
    public const END_OF_LIFE = '01/2027';

    public function __clone()
    {
        parent::__clone();
        $this->requestStackSize = 0;
        $this->resetServices = false;
        $this->handlingHttpCache = false;
    }

    public function boot(): void
    {
        if ($this->booted) {
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

        if (!$this->container) {
            $this->preBoot();
        }

        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->booted = true;
    }

    public function reboot(?string $warmupDir): void
    {
        $this->shutdown();
        $this->warmupDir = $warmupDir;
        $this->boot();
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!$this->booted) {
            return;
        }

        if ($this->getHttpKernel() instanceof TerminableInterface) {
            $this->getHttpKernel()->terminate($request, $response);
        }
    }

    public function shutdown(): void
    {
        parent::shutdown();
        $this->requestStackSize = 0;
        $this->resetServices = false;
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if (!$this->container) {
            $this->preBoot();
        }

        if (HttpKernelInterface::MAIN_REQUEST === $type && !$this->handlingHttpCache && $this->container->has('http_cache')) {
            $this->handlingHttpCache = true;

            try {
                return $this->container->get('http_cache')->handle($request, $type, $catch);
            } finally {
                $this->handlingHttpCache = false;
                $this->resetServices = true;
            }
        }

        $this->boot();
        ++$this->requestStackSize;
        if (!$this->handlingHttpCache) {
            $this->resetServices = true;
        }

        try {
            return $this->getHttpKernel()->handle($request, $type, $catch);
        } finally {
            --$this->requestStackSize;
        }
    }

    protected function getHttpKernel(): HttpKernelInterface
    {
        return $this->container->get('http_kernel');
    }

    public function getBundle(string $name): BundleInterface
    {
        if (!isset($this->bundles[$name])) {
            throw new \InvalidArgumentException(\sprintf('Bundle "%s" does not exist or it is not enabled. Maybe you forgot to add it in the "registerBundles()" method of your "%s.php" file?', $name, get_debug_type($this)));
        }

        return $this->bundles[$name];
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getBuildDir(): string
    {
        return $this->getCacheDir();
    }

    public function getShareDir(): ?string
    {
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

    protected function initializeBundles(): void
    {
        $this->doInitializeBundles();

        foreach ($this->bundles as $name => $bundle) {
            $this->bundles[$name] = !$bundle instanceof BundleInterface ? new BundleAdapter($bundle) : $bundle;
        }
    }

    protected function initializeContainer(): void
    {
        $cachePath = $this->getEffectiveBuildDir().'/'.$this->getContainerClass().'.php';
        $oldMtime = is_file($cachePath) ? filemtime($cachePath) : false;

        $this->doInitializeContainer();

        if (false !== $oldMtime && filemtime($cachePath) === $oldMtime) {
            return;
        }

        $buildDir = $this->container->getParameter('kernel.build_dir');
        $cacheDir = $this->container->getParameter('kernel.cache_dir');
        $preload = $this instanceof WarmableInterface ? $this->warmUp($cacheDir, $buildDir) : [];

        if ($this->container->has('cache_warmer')) {
            $cacheWarmer = $this->container->get('cache_warmer');

            if ($cacheDir !== $buildDir) {
                $cacheWarmer->enableOptionalWarmers();
            }

            $preload = array_merge($preload, $cacheWarmer->warmUp($cacheDir, $buildDir));
        }

        if ($preload && file_exists($preloadFile = $buildDir.'/'.$this->getContainerClass().'.preload.php')) {
            Preloader::append($preloadFile, $preload);
        }
    }

    protected function getKernelParameters(): array
    {
        $parameters = $this->doGetKernelParameters() + [
            'kernel.charset' => $this->getCharset(),
        ];

        foreach ($this->bundles as $name => $bundle) {
            $parameters['kernel.bundles_metadata'][$name]['namespace'] = $bundle->getNamespace();
        }

        return $parameters;
    }

    private function preBoot(): void
    {
        if ($this->debug) {
            $this->startTime = microtime(true);
        }

        $this->initializeBundles();
        $this->initializeContainer();

        $container = $this->container;

        if ($container->hasParameter('kernel.trusted_hosts') && $trustedHosts = $container->getParameter('kernel.trusted_hosts')) {
            Request::setTrustedHosts(\is_array($trustedHosts) ? $trustedHosts : preg_split('/\s*+,\s*+(?![^{]*})/', $trustedHosts));
        }

        if ($container->hasParameter('kernel.trusted_proxies') && $container->hasParameter('kernel.trusted_headers') && $trustedProxies = $container->getParameter('kernel.trusted_proxies')) {
            $trustedHeaders = $container->getParameter('kernel.trusted_headers');

            if (\is_string($trustedHeaders)) {
                $trustedHeaders = array_map('trim', explode(',', $trustedHeaders));
            }

            if (\is_array($trustedHeaders)) {
                $trustedHeaderSet = 0;

                foreach ($trustedHeaders as $header) {
                    if (!\defined($const = Request::class.'::HEADER_'.strtr(strtoupper($header), '-', '_'))) {
                        throw new \InvalidArgumentException(\sprintf('The trusted header "%s" is not supported.', $header));
                    }
                    $trustedHeaderSet |= \constant($const);
                }
            } else {
                $trustedHeaderSet = $trustedHeaders ?? (Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
            }

            Request::setTrustedProxies(\is_array($trustedProxies) ? $trustedProxies : array_map('trim', explode(',', $trustedProxies)), $trustedHeaderSet);
        }
    }

    private function getEffectiveBuildDir(): string
    {
        return $this->warmupDir ?: $this->getBuildDir();
    }
}
