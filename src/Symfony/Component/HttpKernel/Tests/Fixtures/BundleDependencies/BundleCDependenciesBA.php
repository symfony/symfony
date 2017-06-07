<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies;

use Symfony\Component\HttpKernel\Bundle\BundleDependenciesInterface;

class BundleCDependenciesBA implements BundleDependenciesInterface
{
    public function getBundleDependencies($environment, $debug)
    {
        if ('prod_test' === $environment) {
            return array();
        } elseif ($debug) {
            return array('Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies\BundleADependenciesNon' => self::DEP_REQUIRED);
        }

        return array(
            'Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies\BundleBDependenciesA' => self::DEP_REQUIRED,
            'Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies\BundleADependenciesNon' => self::DEP_OPTIONAL,
        );
    }
}
