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

use Symfony\Component\Console\DataCollector\CommandDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('.data_collector.command', CommandDataCollector::class)
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/command.html.twig', 'id' => 'command', 'priority' => 335])
    ;
};
