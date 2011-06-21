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
use Symfony\Component\HttpKernel\HttpKernelInterface;
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
     * Returns an array of bundles to registers.
     *
     * @return array An array of bundle instances.
     */
    function registerBundles();

    /**
     * Loads the container configuration
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    function registerContainerConfiguration(LoaderInterface $loader);

    /**
     * Boots the current kernel.
     */
    function boot();

    /**
     * Shutdowns the kernel.
     *
     * This method is mainly useful when doing functional testing.
     */
    function shutdown();

    /**
     * Gets the registered bundle instances.
     *
     * @return array An array of registered bundle instances
     */
    function getBundles();

    /**
     * Checks if a given class name belongs to an active bundle.
     *
     * @param string $class A class name
     *
     * @return Boolean true if the class belongs to an active bundle, false otherwise
     */
    function isClassInActiveBundle($class);

    /**
     * Returns a bundle and optionally its descendants by its name.
     *
     * @param string  $name  Bundle name
     * @param Boolean $first Whether to return the first bundle only or together with its descendants
     *
     * @return BundleInterface|Array A BundleInterface instance or an array of BundleInterface instances if $first is false
     *
     * @throws \InvalidArgumentException when the bundle is not enabled
     */
    function getBundle($name, $first = true);

    /**
     * Returns the file path for a given resource.
     *
     * A Resource can be a file or a directory.
     *
     * The resource name must follow the following pattern:
     *
     *     @BundleName/path/to/a/file.something
     *
     * where BundleName is the name of the bundle
     * and the remaining part is the relative path in the bundle.
     *
     * If $dir is passed, and the first segment of the path is Resources,
     * this method will look for a file named:
     *
     *     $dir/BundleName/path/without/Resources
     *
     * @param string  $name  A resource name to locate
     * @param string  $dir   A directory where to look for the resource first
     * @param Boolean $first Whether to return the first path or paths for all matching bundles
     *
     * @return string|array The absolute path of the resource or an array if $first is false
     *
     * @throws \InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws \RuntimeException         if the name contains invalid/unsafe characters
     */
    function locateResource($name, $dir = null, $first = true);

    /**
     * Gets the name of the kernel
     *
     * @return string The kernel name
     */
    function getName();

    /**
     * Gets the environment.
     *
     * @return string The current environment
     */
    function getEnvironment();

    /**
     * Checks if debug mode is enabled.
     *
     * @return Boolean true if debug mode is enabled, false otherwise
     */
    function isDebug();

    /**
     * Gets the application root dir.
     *
     * @return string The application root dir
     */
    function getRootDir();

    /**
     * Gets the current container.
     *
     * @return ContainerInterface A ContainerInterface instance
     */
    function getContainer();

    /**
     * Gets the request start time (not available if debug is disabled).
     *
     * @return integer The request start timestamp
     */
    function getStartTime();

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     */
    function getCacheDir();

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     */
    function getLogDir();
}
