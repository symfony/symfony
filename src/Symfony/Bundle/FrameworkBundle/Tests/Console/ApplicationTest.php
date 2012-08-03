<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class ApplicationTest extends TestCase
{
    public function testBundleInterfaceImplementation()
    {
        $bundle = $this->getMock("Symfony\Component\HttpKernel\Bundle\BundleInterface");

        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");
        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)))
        ;

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }
}
