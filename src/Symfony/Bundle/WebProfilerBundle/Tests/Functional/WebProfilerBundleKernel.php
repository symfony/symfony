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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class WebProfilerBundleKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
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
        $routes->import(__DIR__.'/../../Resources/config/routing/profiler.php')->prefix('/_profiler');
        $routes->import(__DIR__.'/../../Resources/config/routing/wdt.php')->prefix('/_wdt');
        $routes->add('_', '/')->controller('kernel::homepageController');
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $config = [
            'secret' => 'foo-secret',
            'profiler' => ['only_exceptions' => false, 'collect_serializer_data' => true],
            'session' => ['handler_id' => null, 'storage_factory_id' => 'session.storage.factory.mock_file', 'cookie-secure' => 'auto', 'cookie-samesite' => 'lax'],
            'router' => ['utf8' => true],
        ];

        $container->loadFromExtension('framework', $config);

        $container->loadFromExtension('web_profiler', [
            'toolbar' => true,
            'intercept_redirects' => false,
        ]);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/cache-'.spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/log-'.spl_object_hash($this);
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register('data_collector.dump', DumpDataCollector::class);
        $container->register('logger', NullLogger::class);
    }

    public function homepageController(): Response
    {
        return new Response('<html><head></head><body>Homepage Controller.</body></html>');
    }
}
