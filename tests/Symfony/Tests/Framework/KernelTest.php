<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Framework;

use Symfony\Framework\Kernel;
use Symfony\Component\DependencyInjection\Loader\LoaderInterface;

class KernelTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSafeName()
    {
        $kernel = new KernelForTest('dev', true, '-foo-');

        $this->assertEquals('foo', $kernel->getSafeName());
    }
}

class KernelForTest extends Kernel
{
    public function __construct($environment, $debug, $name)
    {
        parent::__construct($environment, $debug);

        $this->name = $name;
    }

    public function registerRootDir()
    {
    }

    public function registerBundles()
    {
    }

    public function registerBundleDirs()
    {
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}