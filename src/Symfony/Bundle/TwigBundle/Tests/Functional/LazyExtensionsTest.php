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

class LazyExtensionsTest extends TestCase
{
    public function testExtensionsAreRegisteredThroughTheirAttributes()
    {
        $kernel = new LazyExtensionsKernel('dev', true);
        $kernel->boot();

        $twig = $kernel->getContainer()->get('twig.alias');

        foreach (['path', 'url', 'asset', 'absolute_url', 'relative_path'] as $function) {
            $this->assertNotNull($twig->getFunction($function), \sprintf('The "%s" function is not registered.', $function));
        }
        foreach (['yaml_encode', 'yaml_dump'] as $filter) {
            $this->assertNotNull($twig->getFilter($filter), \sprintf('The "%s" filter is not registered.', $filter));
        }

        $html = $twig->render('check.html.twig');

        $this->assertStringContainsString('/home', $html);
        $this->assertStringContainsString('foo.png', $html);
        $this->assertStringContainsString('{ a: b }', $html);
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
        if (file_exists($dir = sys_get_temp_dir().'/LazyExtensionsKernel')) {
            (new Filesystem())->remove($dir);
        }
    }
}

class LazyExtensionsKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container) {
            $container
                ->loadFromExtension('framework', [
                    'secret' => '$ecret',
                    'router' => [
                        'resource' => __DIR__.'/Resources/config/lazy_routing.yml',
                    ],
                ])
                ->loadFromExtension('twig', [
                    'default_path' => __DIR__.'/templates/lazy',
                ])
                ->setAlias('twig.alias', 'twig')->setPublic(true)
            ;
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/LazyExtensionsKernel/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/LazyExtensionsKernel/logs';
    }
}
