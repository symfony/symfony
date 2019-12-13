<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Kernel;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * A Kernel that provides configuration hooks.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait MicroKernelTrait
{
    /**
     * Add or import routes into your application.
     *
     *     $routes->import('config/routing.yml');
     *     $routes->add('/admin', 'App\Controller\AdminController::dashboard', 'admin_dashboard');
     *
     * @final since Symfony 5.1, override configureRouting() instead
     *
     * @internal since Symfony 5.1, use configureRouting() instead
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
    }

    /**
     * Adds or imports routes into your application.
     *
     *     $routes->import($this->getProjectDir().'/config/*.{yaml,php}');
     *     $routes
     *         ->add('admin_dashboard', '/admin')
     *         ->controller('App\Controller\AdminController::dashboard')
     *     ;
     */
    protected function configureRouting(RoutingConfigurator $routes): void
    {
        @trigger_error(sprintf('Not overriding the "%s()" method is deprecated since Symfony 5.1 and will trigger a fatal error in 6.0.', __METHOD__), E_USER_DEPRECATED);
    }

    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     *     $c->loadFromExtension('framework', [
     *         'secret' => '%secret%'
     *     ]);
     *
     * Or services:
     *
     *     $c->register('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     *     $c->setParameter('halloween', 'lot of fun');
     */
    abstract protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader);

    /**
     * {@inheritdoc}
     */
    public function getProjectDir(): string
    {
        return \dirname((new \ReflectionObject($this))->getFileName(), 2);
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) use ($loader) {
            $container->loadFromExtension('framework', [
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ]);

            if (!$container->hasDefinition('kernel')) {
                $container->register('kernel', static::class)
                    ->setSynthetic(true)
                    ->setPublic(true)
                ;
            }

            $kernelDefinition = $container->getDefinition('kernel');
            $kernelDefinition->addTag('routing.route_loader');

            if ($this instanceof EventSubscriberInterface) {
                $kernelDefinition->addTag('kernel.event_subscriber');
            }

            $this->configureContainer($container, $loader);
            $container->addObjectResource($this);
            $container->fileExists($this->getProjectDir().'/config/bundles.php');
        });
    }

    /**
     * @internal
     */
    public function loadRoutes(LoaderInterface $loader)
    {
        $routes = new RouteCollectionBuilder($loader);
        $this->configureRoutes($routes);
        $collection = $routes->build();

        if (0 !== \count($collection)) {
            @trigger_error(sprintf('Adding routes via the "%s:configureRoutes()" method is deprecated since Symfony 5.1 and will have no effect in 6.0; use "configureRouting()" instead.', self::class), E_USER_DEPRECATED);
        }

        $file = (new \ReflectionObject($this))->getFileName();
        $this->configureRouting(new RoutingConfigurator($collection, $loader, null, $file));

        return $collection;
    }
}
