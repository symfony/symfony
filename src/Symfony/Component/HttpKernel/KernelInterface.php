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
use Symfony\Component\DependencyInjection\Kernel\KernelInterface as BaseKernelInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * The Kernel is the heart of the Symfony system.
 *
 * It manages an environment made of application kernel and bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface KernelInterface extends HttpKernelInterface, BaseKernelInterface
{
    /**
     * Returns an array of bundles to register.
     *
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable;

    /**
     * Loads the container configuration.
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void;

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
     * Gets the charset of the application.
     */
    public function getCharset(): string;
}
