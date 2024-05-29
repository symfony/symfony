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

use Symfony\Component\Workflow\DataCollector\WorkflowDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('data_collector.workflow', WorkflowDataCollector::class)
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/workflow.html.twig',
                'id' => 'workflow',
            ])
            ->args([
                tagged_iterator('workflow', 'name'),
                service('event_dispatcher'),
                service('debug.file_link_formatter'),
            ])
    ;
};
