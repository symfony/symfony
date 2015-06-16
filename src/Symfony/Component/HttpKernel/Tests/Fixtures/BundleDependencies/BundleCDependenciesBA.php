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
        if ($environment === 'prod_test')
            return array();
        else if ($debug)
            return array('Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies\BundleADependenciesNon');

        return array(
            'Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies\BundleBDependenciesA',
            'Symfony\Component\HttpKernel\Tests\Fixtures\BundleDependencies\BundleADependenciesNon',
        );
    }
}
