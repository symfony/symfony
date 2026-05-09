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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface KernelInterface
{
    public function boot(): void;

    public function shutdown(): void;

    /**
     * @return array<string, BundleInterface>
     */
    public function getBundles(): array;

    /**
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

    public function getEnvironment(): string;

    public function isDebug(): bool;

    public function getContainer(): ContainerInterface;

    public function getStartTime(): float;

    public function getProjectDir(): string;

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
     * System caches written at runtime should be stored in the "cache directory".
     * Application caches that are shared between all front-end servers should be stored
     * in the "share directory" when there is one.
     */
    public function getBuildDir(): string;

    /**
     * Returns the share directory.
     *
     * This directory should be used to store data that is shared between all front-end servers.
     * This typically fits application caches.
     */
    public function getShareDir(): ?string;

    public function getLogDir(): ?string;
}
