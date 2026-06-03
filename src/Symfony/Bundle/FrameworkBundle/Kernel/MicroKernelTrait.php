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

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * A Kernel that provides configuration hooks.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait MicroKernelTrait
{
    use KernelTrait {
        registerContainerConfiguration as private doRegisterContainerConfiguration;
        initializeBundles as protected doInitializeBundles;
        initializeContainer as protected doInitializeContainer;
        getKernelParameters as private doGetKernelParameters;
        getBundlesDefinition as private doGetBundlesDefinition;
    }

    public function getLogDir(): string
    {
        return $_SERVER['APP_LOG_DIR'] ?? parent::getLogDir();
    }

    public function registerBundles(): iterable
    {
        if (!is_file($bundlesPath = $this->getBundlesPath())) {
            yield new FrameworkBundle();
        } else {
            yield from parent::registerBundles();
        }
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $this->doRegisterContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ]);

            $kernelDefinition = $container->getDefinition('kernel');
            $kernelDefinition->addTag('controller.service_arguments');
            $kernelDefinition->addTag('routing.route_loader');
            $kernelDefinition->setAutoconfigured(true);
        });
    }

    protected function initializeBundles(): void
    {
        parent::initializeBundles();
    }

    protected function initializeContainer(): void
    {
        parent::initializeContainer();
    }

    /**
     * Adds or imports routes into your application.
     *
     *     $routes->import($this->getConfigDir().'/*.{yaml,php}');
     *     $routes
     *         ->add('admin_dashboard', '/admin')
     *         ->controller('App\Controller\AdminController::dashboard')
     *     ;
     */
    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = preg_replace('{/config$}', '/{config}', $this->getConfigDir());

        $routes->import($configDir.'/{routes}/'.$this->environment.'/*.{php,yaml}');
        $routes->import($configDir.'/{routes}/*.{php,yaml}');

        if (is_file($this->getConfigDir().'/routes.yaml')) {
            $routes->import($configDir.'/routes.yaml');
        } else {
            $routes->import($configDir.'/{routes}.php');
        }

        if ($fileName = (new \ReflectionObject($this))->getFileName()) {
            $routes->import($fileName, 'attribute');
        }
    }

    /**
     * @internal
     */
    public function loadRoutes(LoaderInterface $loader): RouteCollection
    {
        $file = (new \ReflectionObject($this))->getFileName();
        /** @var RoutingPhpFileLoader $kernelLoader */
        $kernelLoader = $loader->getResolver()->resolve($file, 'php');
        $kernelLoader->setCurrentDir(\dirname($file));
        $collection = new RouteCollection();

        $configureRoutes = new \ReflectionMethod($this, 'configureRoutes');
        $configureRoutes->getClosure($this)(new RoutingConfigurator($collection, $kernelLoader, $file, $file, $this->getEnvironment()));

        foreach ($collection as $route) {
            $controller = $route->getDefault('_controller');

            if (\is_array($controller) && [0, 1] === array_keys($controller) && $this === $controller[0]) {
                $route->setDefault('_controller', ['kernel', $controller[1]]);
            } elseif ($controller instanceof \Closure && $this === ($r = new \ReflectionFunction($controller))->getClosureThis() && !$r->isAnonymous()) {
                $route->setDefault('_controller', ['kernel', $r->name]);
            } elseif ($this::class === $controller && method_exists($this, '__invoke')) {
                $route->setDefault('_controller', 'kernel');
            }
        }

        return $collection;
    }

    /**
     * @return array<string, array|bool|string|int|float|\UnitEnum|null>
     */
    protected function getKernelParameters(): array
    {
        // `doGetKernelParameters() + parent::getKernelParameters()` is left-biased on the shared
        // `kernel.bundles_metadata` key, so the namespace written by parent::getKernelParameters()
        // is shadowed. Re-apply it here to keep both `path` (from KernelTrait) and `namespace`.
        $parameters = $this->doGetKernelParameters() + parent::getKernelParameters();

        foreach ($this->bundles as $name => $bundle) {
            $parameters['kernel.bundles_metadata'][$name]['namespace'] = $bundle->getNamespace();
        }

        return $parameters;
    }

    private function getBundlesDefinition(): array
    {
        return $this->doGetBundlesDefinition() ?: [FrameworkBundle::class => ['all' => true]];
    }
}
