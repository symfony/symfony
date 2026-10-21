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

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Controller\TemplateController;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator;
use Symfony\Component\Routing\Loader\YamlFileLoader;

class TemplateRouteTest extends TestCase
{
    public function testTemplateDefaultRendersTheTemplate()
    {
        $this->assertFaqTemplateIsRendered(new TemplateRouteKernel('template_default_routing.yml'));
    }

    public function testTemplateKeyRendersTheTemplate()
    {
        if (!\in_array('template', (new \ReflectionClassConstant(YamlFileLoader::class, 'AVAILABLE_KEYS'))->getValue(), true)) {
            $this->markTestSkipped('symfony/routing 8.2 or higher is required.');
        }

        $this->assertFaqTemplateIsRendered(new TemplateRouteKernel('template_routing.yml'));
    }

    public function testFluentTemplateRendersTheTemplate()
    {
        if (!method_exists(RouteConfigurator::class, 'template')) {
            $this->markTestSkipped('symfony/routing 8.2 or higher is required.');
        }

        $this->assertFaqTemplateIsRendered(new TemplateRouteKernel('template_fluent_routing.php'));
    }

    private function assertFaqTemplateIsRendered(Kernel $kernel): void
    {
        $response = $kernel->handle(Request::create('/faq.html'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("FAQ updated on 2026-10-20\n", $response->getContent());
        $this->assertSame(300, $response->getMaxAge());

        $route = $kernel->getContainer()->get('router')->getRouteCollection()->get('faq');
        $this->assertSame(TemplateController::class.'::templateAction', $route->getDefault('_controller'));
        $this->assertSame('pages/faq.html.twig', $route->getDefault('template'));
        $this->assertFalse($route->hasDefault('_template'));
    }

    #[Before, After]
    protected function deleteTempDir()
    {
        if (file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/TemplateRoute')) {
            (new Filesystem())->remove($dir);
        }
    }
}

class TemplateRouteKernel extends Kernel
{
    public function __construct(
        private string $routingFile,
    ) {
        parent::__construct(pathinfo($routingFile, \PATHINFO_FILENAME), false);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container
                ->loadFromExtension('framework', [
                    'secret' => '$ecret',
                    'form' => ['enabled' => false],
                    'router' => ['resource' => __DIR__.'/Resources/config/'.$this->routingFile],
                ])
                ->loadFromExtension('twig', [
                    'default_path' => __DIR__.'/templates',
                ])
            ;
        });
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/TemplateRoute';
    }
}
