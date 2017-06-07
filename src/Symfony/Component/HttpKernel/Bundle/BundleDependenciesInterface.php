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
 * Adds capability to let Bundles specify other bundles they need to load (before this one), both required and optional
 * dependencies.
 *
 * This allows you to define only the bundle you want to use in registerBundles(), but don't need to take care about
 * registering the dependencies it uses, and you won't need to make any changes in your kernel if those dependencies
 * change.
 *
 * NOTE: In this interface bundle dependencies are returned as FQN strings because several bundles (and root) might
 * register the same bundle. This means dependencies can not have arguments in its constructor, reflecting best practice
 * in Symfony of not having any logic in Bundle constructors given it is executed on every Kernel boot.
 *
 * NOTE2: This functionality is not a bundle plugin system, but rather a way to set your upstream dependencies,
 *        or in the case of a distribution bundle, all bundles they are to be used with.
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
     * @const Flag a Bundle Dependency as required, if missing throw exception
     *
     * @link \Symfony\Component\HttpKernel\Exception\DependencyMismatchException
     */
    const DEP_REQUIRED = true;

    /**
     * @const Flag a Bundle Dependency as optional, if missing silently ignore it
     */
    const DEP_OPTIONAL = false;

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
     *     public function getBundleDependencies($environment, $debug)
     *     {
     *         $dependencies = array();
     *
     *         // If you need to load some bundles only in dev using $environment (or in $debug)
     *         if ($environment === 'dev') {
     *              $dependencies['Egulias\SecurityDebugCommandBundle\EguliasSecurityDebugCommandBundle'] = self::DEP_REQUIRED;
     *         }
     *
     *         return $dependencies + array(
     *             // Keys must be Fully Qualified Name (FQN) for the class to avoid bundles being loaded several times
     *             // Note: Currently, use of DEP_OPTIONAL causes a *uncached* file system call on boot (every request)
     *             //       if dependency is missing, in a future versions this information might be cached.
     *             'FOS\HttpCacheBundle\FOSHttpCacheBundle' => self::DEP_OPTIONAL,
     *
     *             // If you require PHP 5.5+ it is possible to use `::class` constant for required dependencies:
     *             Oneup\FlysystemBundle\OneupFlysystemBundle::class => self::DEP_REQUIRED,
     *         );
     *     }
     * }
     * ```
     *
     * @param string $environment The current environment
     * @param bool   $debug       Whether to debugging is enabled or not
     *
     * @return mixed[string] An array where key is bundle class (FQN) names as strings, and value DEP_* constants
     *
     * @api
     */
    public function getBundleDependencies($environment, $debug);
}
