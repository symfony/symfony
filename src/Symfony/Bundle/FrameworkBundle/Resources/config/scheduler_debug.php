<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Scheduler\DataCollector\SchedulerDataCollector;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('scheduler.data_collector', SchedulerDataCollector::class)
        ->tag('data_collector', [
            'id' => 'scheduler',
            'template' => '@WebProfiler/Collector/scheduler.html.twig',
            'priority' => 255,
        ])
    ;
};
