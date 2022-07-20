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
 *
 * @method string getProjectDir() Gets the project dir (path of the project's composer file) - not defining it is deprecated since Symfony 4.2
 */
interface KernelInterface extends HttpKernelInterface
{
    /**
     * Returns an array of bundles to register.
     *
     * @return iterable|BundleInterface[] An iterable of bundle instances
     */
    public function registerBundles();

    /**
     * Loads the container configuration.
     */
    public function registerContainerConfiguration(LoaderInterface $loader);

    /**
     * Boots the current kernel.
     */
    public function boot();

    /**
     * Shutdowns the kernel.
     *
     * This method is mainly useful when doing functional testing.
     */
    public function shutdown();

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
    public function getBundle($name);

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
     * @param string $name A resource name to locate
     *
     * @return string|array The absolute path of the resource or an array if $first is false (array return value is deprecated)
     *
     * @throws \InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws \RuntimeException         if the name contains invalid/unsafe characters
     */
    public function locateResource($name/* , $dir = null, $first = true */);

    /**
     * Gets the name of the kernel.
     *
     * @return string The kernel name
     *
     * @deprecated since Symfony 4.2
     */
    public function getName();

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    public function getEnvironment();

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool true if debug mode is enabled, false otherwise
     */
    public function isDebug();

    /**
     * Gets the application root dir (path of the project's Kernel class).
     *
     * @return string The Kernel root dir
     *
     * @deprecated since Symfony 4.2
     */
    public function getRootDir();

    /**
     * Gets the current container.
     *
     * @return ContainerInterface
     */
    public function getContainer();

    /**
     * Gets the request start time (not available if debug is disabled).
     *
     * @return float The request start timestamp
     */
    public function getStartTime();

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     */
    public function getCacheDir();

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     */
    public function getLogDir();

    /**
     * Gets the charset of the application.
     *
     * @return string The charset
     */
    public function getCharset();
}
