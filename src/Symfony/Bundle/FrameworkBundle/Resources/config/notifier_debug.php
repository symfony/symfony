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

use Symfony\Component\Notifier\DataCollector\NotificationDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('notifier.data_collector', NotificationDataCollector::class)
            ->args([service('notifier.logger_notification_listener')])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/notifier.html.twig', 'id' => 'notifier'])
    ;
};
