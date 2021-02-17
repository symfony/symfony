<?php

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\App;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver;
use Symfony\Bridge\PsrHttpMessage\EventListener\PsrResponseListener;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\App\Controller\PsrRequestController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel44 extends SymfonyKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $routes->add('/server-request', PsrRequestController::class.'::serverRequestAction')->setMethods(['GET']);
        $routes->add('/request', PsrRequestController::class.'::requestAction')->setMethods(['POST']);
        $routes->add('/message', PsrRequestController::class.'::messageAction')->setMethods(['PUT']);
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'for your eyes only',
            'test' => true,
        ]);

        $container->register('nyholm.psr_factory', Psr17Factory::class);
        $container->setAlias(ResponseFactoryInterface::class, 'nyholm.psr_factory');
        $container->setAlias(ServerRequestFactoryInterface::class, 'nyholm.psr_factory');
        $container->setAlias(StreamFactoryInterface::class, 'nyholm.psr_factory');
        $container->setAlias(UploadedFileFactoryInterface::class, 'nyholm.psr_factory');

        $container->register(HttpFoundationFactoryInterface::class, HttpFoundationFactory::class)->setAutowired(true)->setAutoconfigured(true);
        $container->register(HttpMessageFactoryInterface::class, PsrHttpFactory::class)->setAutowired(true)->setAutoconfigured(true);
        $container->register(PsrResponseListener::class)->setAutowired(true)->setAutoconfigured(true);
        $container->register(PsrServerRequestResolver::class)->setAutowired(true)->setAutoconfigured(true);

        $container->register('logger', NullLogger::class);
        $container->register(PsrRequestController::class)->setPublic(true)->setAutowired(true);
    }
}
