<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fixtures;

use Symphony\Component\HttpKernel\Kernel;
use Symphony\Component\Config\Loader\LoaderInterface;

class KernelForOverrideName extends Kernel
{
    protected $name = 'overridden';

    public function registerBundles()
    {
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}
