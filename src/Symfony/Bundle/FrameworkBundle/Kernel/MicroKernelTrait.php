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

use Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Bundle\FrameworkBundle\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\AbstractConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader as ContainerPhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
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
                'secret' => '%env(APP_SECRET)%',
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ]);

            if (!$container->hasDefinition('kernel')) {
                $container->register('kernel', static::class)
                    ->addTag('controller.service_arguments')
                    ->setAutoconfigured(true)
                    ->setSynthetic(true)
                    ->setPublic(true)
                ;
            }

            $kernelDefinition = $container->getDefinition('kernel');
            $kernelDefinition->addTag('routing.route_loader');

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

            // the user has opted into using the ContainerConfigurator
            /* @var ContainerPhpFileLoader $kernelLoader */
            $kernelLoader = $loader->getResolver()->resolve($file);
            $kernelLoader->setCurrentDir(\dirname($file));
            $instanceof = &\Closure::bind(function &() { return $this->instanceof; }, $kernelLoader, $kernelLoader)();

            $valuePreProcessor = AbstractConfigurator::$valuePreProcessor;
            AbstractConfigurator::$valuePreProcessor = function ($value) {
                return $this === $value ? new Reference('kernel') : $value;
            };

            try {
                $this->configureContainer(new ContainerConfigurator($container, $kernelLoader, $instanceof, $file, $file), $loader);
            } finally {
                $instanceof = [];
                $kernelLoader->registerAliasesForSinglyImplementedInterfaces();
                AbstractConfigurator::$valuePreProcessor = $valuePreProcessor;
            }

            $container->setAlias(static::class, 'kernel')->setPublic(true);
        });
    }

    /**
     * @internal
     */
    public function loadRoutes(LoaderInterface $loader)
    {
        $file = (new \ReflectionObject($this))->getFileName();
        /* @var RoutingPhpFileLoader $kernelLoader */
        $kernelLoader = $loader->getResolver()->resolve($file);
        $kernelLoader->setCurrentDir(\dirname($file));
        $collection = new RouteCollection();

        try {
            $this->configureRoutes(new RoutingConfigurator($collection, $kernelLoader, $file, $file));

            foreach ($collection as $route) {
                $controller = $route->getDefault('_controller');

                if (\is_array($controller) && [0, 1] === array_keys($controller) && $this === $controller[0]) {
                    $route->setDefault('_controller', ['kernel', $controller[1]]);
                }
            }

            return $collection;
        } catch (\TypeError $e) {
            if (0 !== strpos($e->getMessage(), sprintf('Argument 1 passed to %s::configureRoutes() must be an instance of %s,', static::class, RouteCollectionBuilder::class))) {
                throw $e;
            }
        }

        trigger_deprecation('symfony/framework-bundle', '5.1', 'Using type "%s" for argument 1 of method "%s:configureRoutes()" is deprecated, use "%s" instead.', RouteCollectionBuilder::class, self::class, RoutingConfigurator::class);

        $routes = new RouteCollectionBuilder($loader);
        $this->configureRoutes($routes);

        return $routes->build();
    }
}
