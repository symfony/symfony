<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\Tests\Functional;

use Symphony\Component\HttpKernel\Kernel;
use Symphony\Component\Config\Loader\LoaderInterface;
use Symphony\Component\Filesystem\Filesystem;
use Symphony\Bundle\FrameworkBundle\FrameworkBundle;
use Symphony\Bundle\TwigBundle\Tests\TestCase;
use Symphony\Bundle\TwigBundle\TwigBundle;

class NoTemplatingEntryTest extends TestCase
{
    public function test()
    {
        $kernel = new NoTemplatingEntryKernel('dev', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $content = $container->get('twig')->render('index.html.twig');
        $this->assertContains('{ a: b }', $content);
    }

    protected function setUp()
    {
        $this->deleteTempDir();
    }

    protected function tearDown()
    {
        $this->deleteTempDir();
    }

    protected function deleteTempDir()
    {
        if (!file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/NoTemplatingEntryKernel')) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }
}

class NoTemplatingEntryKernel extends Kernel
{
    public function registerBundles()
    {
        return array(new FrameworkBundle(), new TwigBundle());
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container
                ->loadFromExtension('framework', array(
                    'secret' => '$ecret',
                    'form' => array('enabled' => false),
                ))
                ->loadFromExtension('twig', array( // to be removed in 5.0 relying on default
                    'strict_variables' => false,
                ))
            ;
        });
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/NoTemplatingEntryKernel/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/NoTemplatingEntryKernel/logs';
    }
}
