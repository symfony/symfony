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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollection;
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
     * Adds or imports routes into your application.
     *
     *     $routes->import($this->getProjectDir().'/config/*.{yaml,php}');
     *     $routes
     *         ->add('admin_dashboard', '/admin')
     *         ->controller('App\Controller\AdminController::dashboard')
     *     ;
     */
    abstract protected function configureRoutes(RoutingConfigurator $routes);

    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     *     $c->extension('framework', [
     *         'secret' => '%secret%'
     *     ]);
     *
     * Or services:
     *
     *     $c->services()->set('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     *     $c->parameters()->set('halloween', 'lot of fun');
     */
    abstract protected function configureContainer(ContainerConfigurator $c);

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

            $container->addObjectResource($this);
            $container->fileExists($this->getProjectDir().'/config/bundles.php');

            try {
                $this->configureContainer($container, $loader);

                return;
            } catch (\TypeError $e) {
                $file = $e->getFile();

                if (0 !== strpos($e->getMessage(), sprintf('Argument 1 passed to %s::configureContainer() must be an instance of %s,', static::class, ContainerConfigurator::class))) {
                    throw $e;
                }
            }

            $kernelLoader = $loader->getResolver()->resolve($file);
            $kernelLoader->setCurrentDir(\dirname($file));
            $instanceof = &\Closure::bind(function &() { return $this->instanceof; }, $kernelLoader, $kernelLoader)();

            try {
                $this->configureContainer(new ContainerConfigurator($container, $kernelLoader, $instanceof, $file, $file), $loader);
            } finally {
                $instanceof = [];
                $kernelLoader->registerAliasesForSinglyImplementedInterfaces();
            }
        });
    }

    /**
     * @internal
     */
    public function loadRoutes(LoaderInterface $loader)
    {
        $file = (new \ReflectionObject($this))->getFileName();
        $kernelLoader = $loader->getResolver()->resolve($file);
        $kernelLoader->setCurrentDir(\dirname($file));
        $collection = new RouteCollection();

        try {
            $this->configureRoutes(new RoutingConfigurator($collection, $kernelLoader, $file, $file));

            return $collection;
        } catch (\TypeError $e) {
            if (0 !== strpos($e->getMessage(), sprintf('Argument 1 passed to %s::configureRoutes() must be an instance of %s,', static::class, RouteCollectionBuilder::class))) {
                throw $e;
            }
        }

        @trigger_error(sprintf('Using type "%s" for argument 1 of method "%s:configureRoutes()" is deprecated since Symfony 5.1, use "%s" instead.', RouteCollectionBuilder::class, self::class, RoutingConfigurator::class), E_USER_DEPRECATED);

        $routes = new RouteCollectionBuilder($loader);
        $this->configureRoutes($routes);

        return $routes->build();
    }
}
