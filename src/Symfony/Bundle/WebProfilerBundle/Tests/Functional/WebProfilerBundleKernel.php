<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Functional;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class WebProfilerBundleKernel extends Kernel
{
    use MicroKernelTrait;

    private $interceptRedirects;

    public function __construct(bool $interceptRedirects = false)
    {
        $this->interceptRedirects = $interceptRedirects;
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new WebProfilerBundle(),
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../Resources/config/routing/profiler.xml')->prefix('/_profiler');
        $routes->import(__DIR__.'/../../Resources/config/routing/wdt.xml')->prefix('/_wdt');
        $routes->add('_', '/')->controller('kernel::homepageController');
        $routes->add('redirect', '/redirect')->controller('kernel::redirectController');
    }

    protected function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader): void
    {
        $containerBuilder->loadFromExtension('framework', [
            'secret' => 'foo-secret',
            'profiler' => ['only_exceptions' => false],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router' => ['utf8' => true],
        ]);

        $containerBuilder->loadFromExtension('web_profiler', [
            'toolbar' => true,
            'intercept_redirects' => $this->interceptRedirects,
        ]);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/cache-'.spl_object_hash($this).($this->interceptRedirects ? '-intercept' : '');
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/log-'.spl_object_hash($this);
    }

    protected function build(ContainerBuilder $container)
    {
        $container->register('data_collector.dump', DumpDataCollector::class);
        $container->register('logger', NullLogger::class);
    }

    public function homepageController()
    {
        return new Response('<html><head></head><body>Homepage Controller.</body></html>');
    }

    public function redirectController(Request $request)
    {
        return new RedirectResponse($request->query->get('location'));
    }
}
