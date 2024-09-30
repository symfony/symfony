<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test\Fixtures;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
        ];
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $container->extension('framework', [
            'router' => [
                'utf8' => true,
            ],
            'test' => true,
        ]);
        $container->services()->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('ok', '/200')->controller([WebTestCaseController::class, 'ok']);
        $routes->add('not_found', '/404')->controller([WebTestCaseController::class, 'notFound']);
        $routes->add('moved_permanently', '/301')->controller([WebTestCaseController::class, 'movedPermanently']);
        $routes->add('found', '/302')->controller([WebTestCaseController::class, 'found']);
        $routes->add('internal_server_error', '/500')->controller([WebTestCaseController::class, 'internalServerError']);
        $routes->add('custom_format', '/custom-format')->controller([WebTestCaseController::class, 'customFormat']);
        $routes->add('jsonld_format', '/jsonld-format')->controller([WebTestCaseController::class, 'jsonldFormat']);
        $routes->add('no_format', '/no-format')->controller([WebTestCaseController::class, 'noFormat']);
        $routes->add('crawler', '/crawler/{content}')->controller([WebTestCaseController::class, 'crawler']);
        $routes->add('request_attribute', '/request-attribute')->controller([WebTestCaseController::class, 'requestAttribute']);
        $routes->add('homepage', '/homepage/{foo}')->controller([WebTestCaseController::class, 'homepage']);
    }
}
