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

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\Locator\ChainScheduleConfigLocator;
use Symfony\Component\Scheduler\Messenger\ScheduleTransportFactory;
use Symfony\Component\Scheduler\State\StateFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('scheduler.messenger_transport_factory', ScheduleTransportFactory::class)
            ->args([
                service('clock'),
                service('scheduler.schedule_config_locator'),
                service('scheduler.state_factory'),
            ])
            ->tag('messenger.transport_factory')

        ->set('scheduler.schedule_config_locator', ChainScheduleConfigLocator::class)
            ->args([
                tagged_iterator('scheduler.schedule_config_locator'),
            ])

        ->set('scheduler.state_factory', StateFactory::class)
            ->args([
                service('scheduler.lock_locator'),
                service('scheduler.cache_locator'),
            ])

        ->set('scheduler.lock_locator', ServiceLocator::class)
            ->args([
                [],
            ])
            ->tag('container.service_locator')

        ->set('scheduler.cache_locator', ServiceLocator::class)
            ->args([
                [],
            ])
            ->tag('container.service_locator')
    ;
};
