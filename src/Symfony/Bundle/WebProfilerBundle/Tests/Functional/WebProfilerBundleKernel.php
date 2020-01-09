<?php

namespace Symfony\Bundle\WebProfilerBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class WebProfilerBundleKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', false);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        if (null === $this->name) {
            $this->name = parent::getName().substr(md5(__CLASS__), -16);
        }

        return $this->name;
    }

    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new WebProfilerBundle(),
        ];
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->import(__DIR__.'/../../Resources/config/routing/profiler.xml', '/_profiler');
        $routes->import(__DIR__.'/../../Resources/config/routing/wdt.xml', '/_wdt');
        $routes->add('/', 'kernel:homepageController');
    }

    protected function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader)
    {
        $containerBuilder->loadFromExtension('framework', [
            'secret' => 'foo-secret',
            'profiler' => ['only_exceptions' => false],
            'session' => ['storage_id' => 'session.storage.mock_file'],
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

    public function homepageController()
    {
        return new Response('<html><head></head><body>Homepage Controller.</body></html>');
    }
}
