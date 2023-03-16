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

use Symfony\Component\Scheduler\Messenger\SchedulerTransportFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('scheduler.messenger_transport_factory', SchedulerTransportFactory::class)
            ->args([
                tagged_locator('scheduler.schedule_provider', 'name'),
                service('clock'),
            ])
            ->tag('messenger.transport_factory')
    ;
};
