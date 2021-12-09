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

use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('mailer.data_collector', MessageDataCollector::class)
            ->args([
                service('mailer.message_logger_listener'),
            ])
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/mailer.html.twig',
                'id' => 'mailer',
            ])
    ;
};
