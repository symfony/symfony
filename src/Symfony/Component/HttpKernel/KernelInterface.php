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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * The Kernel is the heart of the Symfony system.
 *
 * It manages an environment made of bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface KernelInterface extends HttpKernelInterface, \Serializable
{
    /**
     * Returns an array of bundles to register.
     *
     * @return BundleInterface[] An array of bundle instances
     */
    public function registerBundles();

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
     * @return BundleInterface[] An array of registered bundle instances
     */
    public function getBundles();

    /**
     * Returns a bundle.
     *
     * @param string $name Bundle name
     *
     * @return BundleInterface A BundleInterface instance
     *
     * @throws \InvalidArgumentException when the bundle is not enabled
     */
    public function getBundle(string $name): BundleInterface;

    /**
     * Returns the file path for a given resource.
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
     * If $dir is passed, and the first segment of the path is "Resources",
     * this method will look for a file named:
     *
     *     $dir/<BundleName>/path/without/Resources
     *
     * before looking in the bundle resource folder.
     *
     * @param string $name  A resource name to locate
     * @param string $dir   A directory where to look for the resource first
     * @param bool   $first Whether to return the first path or paths for all matching bundles
     *
     * @return string|array The absolute path of the resource or an array if $first is false
     *
     * @throws \InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws \RuntimeException         if the name contains invalid/unsafe characters
     */
    public function locateResource(string $name, string $dir = null, bool $first = true);

    /**
     * Gets the name of the kernel.
     *
     * @return string The kernel name
     */
    public function getName(): string;

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    public function getEnvironment(): string;

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool true if debug mode is enabled, false otherwise
     */
    public function isDebug(): bool;

    /**
     * Gets the application root dir (path of the project's Kernel class).
     *
     * @return string The Kernel root dir
     */
    public function getRootDir(): string;

    /**
     * Gets the current container.
     *
     * @return ContainerInterface A ContainerInterface instance
     */
    public function getContainer(): ContainerInterface;

    /**
     * Gets the request start time (not available if debug is disabled).
     *
     * @return int The request start timestamp
     */
    public function getStartTime(): int;

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     */
    public function getCacheDir(): string;

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     */
    public function getLogDir(): string;

    /**
     * Gets the charset of the application.
     *
     * @return string The charset
     */
    public function getCharset(): string;
}
