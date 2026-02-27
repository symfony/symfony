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

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The Kernel is the heart of the Symfony system.
 *
 * It manages an environment made of application kernel and bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface KernelInterface extends HttpKernelInterface
{
    /**
     * Returns an array of bundles to register.
     *
     * @return iterable<mixed, BundleInterface>
     */
    public function registerBundles(): iterable;

    /**
     * Loads the container configuration.
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void;

    /**
     * Boots the current kernel.
     */
    public function boot(): void;

    /**
     * Shutdowns the kernel.
     *
     * This method is mainly useful when doing functional testing.
     */
    public function shutdown(): void;

    /**
     * Gets the registered bundle instances.
     *
     * @return array<string, BundleInterface>
     */
    public function getBundles(): array;

    /**
     * Returns a bundle.
     *
     * @throws \InvalidArgumentException when the bundle is not enabled
     */
    public function getBundle(string $name): BundleInterface;

    /**
     * Returns the file path for a given bundle resource.
     *
     * A Resource can be a file or a directory.
     *
     * The resource name must follow the following pattern:
     *
     *     "@BundleName/path/to/a/file.something"
     *
     * where BundleName is the name of the bundle
     * and the remaining part is the relative path in the bundle.
     *
     * @throws \InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws \RuntimeException         if the name contains invalid/unsafe characters
     */
    public function locateResource(string $name): string;

    /**
     * Gets the environment.
     */
    public function getEnvironment(): string;

    /**
     * Checks if debug mode is enabled.
     */
    public function isDebug(): bool;

    /**
     * Gets the project dir (path of the project's composer file).
     */
    public function getProjectDir(): string;

    /**
     * Gets the current container.
     */
    public function getContainer(): ContainerInterface;

    /**
     * Gets the request start time (not available if debug is disabled).
     */
    public function getStartTime(): float;

    /**
     * Gets the cache directory.
     *
     * This directory should be used for caches that are written at runtime.
     * For caches and artifacts that can be warmed at compile-time and deployed as read-only,
     * use the "build directory" returned by the {@see getBuildDir()} method.
     */
    public function getCacheDir(): string;

    /**
     * Returns the build directory.
     *
     * This directory should be used to store build artifacts, and can be read-only at runtime.
     * System caches written at runtime should be stored in the "cache directory" ({@see KernelInterface::getCacheDir()}).
     * Application caches that are shared between all front-end servers should be stored
     * in the "share directory" ({@see KernelInterface::getShareDir()}).
     */
    public function getBuildDir(): string;

    /**
     * Returns the share directory.
     *
     * This directory should be used to store data that is shared between all front-end servers.
     * This typically fits application caches.
     */
    public function getShareDir(): ?string;

    /**
     * Gets the log directory.
     */
    public function getLogDir(): string;

    /**
     * Gets the charset of the application.
     */
    public function getCharset(): string;
}
