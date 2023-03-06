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

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class NoTemplatingEntryTest extends TestCase
{
    public function test()
    {
        $kernel = new NoTemplatingEntryKernel('dev', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $content = $container->get('twig.alias')->render('index.html.twig');
        $this->assertStringContainsString('{ a: b }', $content);
    }

    protected function setUp(): void
    {
        $this->deleteTempDir();
    }

    protected function tearDown(): void
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
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container
                ->loadFromExtension('framework', [
                    'http_method_override' => false,
                    'secret' => '$ecret',
                    'form' => ['enabled' => false],
                ])
                ->loadFromExtension('twig', [
                    'default_path' => __DIR__.'/templates',
                ])
                ->setAlias('twig.alias', 'twig')->setPublic(true)
            ;
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/NoTemplatingEntryKernel/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/NoTemplatingEntryKernel/logs';
    }
}
