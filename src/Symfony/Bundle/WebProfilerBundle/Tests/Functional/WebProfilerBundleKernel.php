<?php

namespace Symfony\Bundle\WebProfilerBundle\Tests\Functional;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class WebProfilerBundleKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', false);
    }

    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new WebProfilerBundle(),
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes)
    {
        $routes->import(__DIR__.'/../../Resources/config/routing/profiler.xml')->prefix('/_profiler');
        $routes->import(__DIR__.'/../../Resources/config/routing/wdt.xml')->prefix('/_wdt');
        $routes->add('_', '/')->controller('kernel::homepageController');
    }

    protected function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader)
    {
        $containerBuilder->loadFromExtension('framework', [
            'secret' => 'foo-secret',
            'profiler' => ['only_exceptions' => false],
            'session' => ['storage_id' => 'session.storage.mock_file'],
            'router' => ['utf8' => true],
        ]);

        $containerBuilder->loadFromExtension('web_profiler', [
            'toolbar' => true,
            'intercept_redirects' => false,
        ]);
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().'/cache-'.spl_object_hash($this);
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().'/log-'.spl_object_hash($this);
    }

    protected function build(ContainerBuilder $container)
    {
        $container->register('logger', NullLogger::class);
    }

    public function homepageController()
    {
        return new Response('<html><head></head><body>Homepage Controller.</body></html>');
    }
}
