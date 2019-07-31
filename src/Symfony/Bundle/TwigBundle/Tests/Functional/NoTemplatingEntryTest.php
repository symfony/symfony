<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Functional;

use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class NoTemplatingEntryTest extends TestCase
{
    use ForwardCompatTestTrait;

    public function test()
    {
        $kernel = new NoTemplatingEntryKernel('dev', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $content = $container->get('twig')->render('index.html.twig');
        $this->assertContains('{ a: b }', $content);
    }

    private function doSetUp()
    {
        $this->deleteTempDir();
    }

    private function doTearDown()
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
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', [
                'secret' => '$ecret',
                'form' => ['enabled' => false],
            ]);
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
