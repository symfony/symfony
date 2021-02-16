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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends SymfonyKernel
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

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes
            ->add('server_request', '/server-request')->controller([PsrRequestController::class, 'serverRequestAction'])->methods(['GET'])
            ->add('request', '/request')->controller([PsrRequestController::class, 'requestAction'])->methods(['POST'])
            ->add('message', '/message')->controller([PsrRequestController::class, 'messageAction'])->methods(['PUT'])
        ;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'router' => ['utf8' => true],
            'secret' => 'for your eyes only',
            'test' => true,
        ]);

        $container->services()
            ->set('nyholm.psr_factory', Psr17Factory::class)
            ->alias(ResponseFactoryInterface::class, 'nyholm.psr_factory')
            ->alias(ServerRequestFactoryInterface::class, 'nyholm.psr_factory')
            ->alias(StreamFactoryInterface::class, 'nyholm.psr_factory')
            ->alias(UploadedFileFactoryInterface::class, 'nyholm.psr_factory')
        ;

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->set(HttpFoundationFactoryInterface::class, HttpFoundationFactory::class)
            ->set(HttpMessageFactoryInterface::class, PsrHttpFactory::class)
            ->set(PsrResponseListener::class)
            ->set(PsrServerRequestResolver::class)
        ;

        $container->services()
            ->set('logger', NullLogger::class)
            ->set(PsrRequestController::class)->public()->autowire()
        ;
    }
}
