<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

/**
 * Class BundleDependenciesInterface.
 *
 * Adds capability to let Bundles specify other bundles they need to load (before this one).
 *
 * This allows you to define only the bundle you want to use in registerBundles(), but don't need to take care about
 * registering the dependencies it uses, and you won't need to make any changes in your kernel if those dependencies
 * changes.
 *
 * In this interface bundle dependencies are returned as FQN strings because several bundles (and root) might register
 * the same bundle. This means dependencies can not have dependencies in its constructor, reflecting best practice in
 * Symfony of not having any logic in Bundle constructors given it is executed on every Kernel boot.
 *
 * @author André Roemcke <andre.romcke@ez.no>
 *
 * @since 2.8
 *
 * @api
 */
interface BundleDependenciesInterface
{
    /**
     * Returns an array of bundle dependencies Kernel should register on boot.
     *
     * Dependencies will be registered before current bundle, implying current bundle *MUST* be loaded after as it
     * for instance extends it.
     *
     * Example of use:
     * ```php
     * class AcmeBundle extends Bundle implements BundleDependenciesInterface
     * {
     *     public function getBundleDependencies()
     *     {
     *         return array(
     *
     *             // All values must be FQN strings to avoid bundles being loaded several times
     *             'FOS\HttpCacheBundle\FOSHttpCacheBundle',
     *
     *             // If you require PHP 5.5 or higher it is better to use `::class` constant:
     *             Oneup\FlysystemBundle\OneupFlysystemBundle::class
     *         );
     *     }
     * }
     * ```
     *
     *
     * @return string[] An array of bundle class (FQN) names as strings.
     *
     * @api
     */
    public function getBundleDependencies();
}
