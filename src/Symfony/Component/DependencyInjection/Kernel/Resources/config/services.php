<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Clock\ClockInterface as PsrClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\SelfCheckingResourceChecker;
use Symfony\Component\Config\ResourceCheckerConfigCacheFactory;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResourceChecker;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\Kernel\FileLocator;
use Symfony\Component\DependencyInjection\Kernel\KernelInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('kernel')
            ->synthetic()
            ->public()
        ->alias(KernelInterface::class, 'kernel')

        ->set('parameter_bag', ContainerBag::class)
            ->args([
                service('service_container'),
            ])
        ->alias(ContainerBagInterface::class, 'parameter_bag')
        ->alias(ParameterBagInterface::class, 'parameter_bag')

        ->set('event_dispatcher', EventDispatcher::class)
            ->public()
            ->tag('container.hot_path')
            ->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher'])
        ->alias(EventDispatcherInterface::class, 'event_dispatcher')
        ->alias(ContractsEventDispatcherInterface::class, 'event_dispatcher')
        ->alias(PsrEventDispatcherInterface::class, 'event_dispatcher')

        ->set('filesystem', Filesystem::class)
        ->alias(Filesystem::class, 'filesystem')

        ->set('file_locator', FileLocator::class)
            ->args([
                service('kernel'),
            ])
        ->alias(FileLocator::class, 'file_locator')

        ->set('config_cache_factory', ResourceCheckerConfigCacheFactory::class)
            ->args([
                tagged_iterator('config_cache.resource_checker'),
            ])

        ->set('dependency_injection.config.container_parameters_resource_checker', ContainerParametersResourceChecker::class)
            ->args([
                service('service_container'),
            ])
            ->tag('config_cache.resource_checker', ['priority' => -980])

        ->set('config.resource.self_checking_resource_checker', SelfCheckingResourceChecker::class)
            ->tag('config_cache.resource_checker', ['priority' => -990])

        ->set('reverse_container', ReverseContainer::class)
            ->args([
                service('service_container'),
                service_locator([]),
            ])
        ->alias(ReverseContainer::class, 'reverse_container')

        ->set('services_resetter', ServicesResetter::class)
            ->public()
        ->alias(ServicesResetterInterface::class, 'services_resetter')

        ->set('container.env_var_processor', EnvVarProcessor::class)
            ->args([
                service('service_container'),
                tagged_iterator('container.env_var_loader'),
            ])
            ->tag('container.env_var_processor')
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('clock', Clock::class)
        ->alias(ClockInterface::class, 'clock')
        ->alias(PsrClockInterface::class, 'clock')

        // register as abstract and excluded, aka not-autowirable types
        ->set(LoaderInterface::class)->abstract()->tag('container.excluded')
    ;
};
