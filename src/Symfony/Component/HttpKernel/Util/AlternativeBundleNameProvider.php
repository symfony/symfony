<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Util;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class AlternativeBundleNameProvider
{
    /** @var KernelInterface */
    private $kernel;

    /**
     * AlternativeBundleNameProvider constructor.
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Attempts to find a bundle that is *similar* to the given bundle name.
     */
    public function findAlternative(string $nonExistentBundleName): ?string
    {
        $bundleNames = array_map(function (BundleInterface $b) {
            return $b->getName();
        }, $this->kernel->getBundles());

        $alternative = null;
        $shortest = null;
        foreach ($bundleNames as $bundleName) {
            // if there's a partial match, return it immediately
            if (false !== strpos($bundleName, $nonExistentBundleName)) {
                return $bundleName;
            }

            $lev = levenshtein($nonExistentBundleName, $bundleName);
            if ($lev <= strlen($nonExistentBundleName) / 3 && (null === $alternative || $lev < $shortest)) {
                $alternative = $bundleName;
                $shortest = $lev;
            }
        }

        return $alternative;
    }
}
