<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMaps\Tests\App;

use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new WebProfilerBundle();
        yield new DebugBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'ChangeMe',
            'test' => 'test' === ($_SERVER['APP_ENV'] ?? 'dev'),
            'router' => [
                'utf8' => true,
            ],
            'profiler' => [
                'only_exceptions' => false,
            ],
            'importmap' => [
                'enabled' => true,
                'provider' => 'unpkg',
                //'api' => 'https://notfound.example.com',
            ],
        ]);

        $container->extension('web_profiler', [
            'toolbar' => true,
            'intercept_redirects' => false,
        ]);

        $container->extension('twig', [
            'importmap' => [
                'polyfill' => 'https://ga.jspm.io/npm:es-module-shims@1.6.2/dist/es-module-shims.js'
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@FrameworkBundle/Resources/config/routing/importmap.xml');
        $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml')->prefix('/_wdt');
        $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml')->prefix('/_profiler');

        $routes->add('home', '/')->controller(TemplateController::class)->defaults(['template' => 'home.html.twig']);
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }
}
