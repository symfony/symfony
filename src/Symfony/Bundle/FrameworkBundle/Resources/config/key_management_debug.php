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

use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('key_management.data_collector', KeyManagementDataCollector::class)
            ->args([abstract_arg('names of the configured clients')])
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/key_management.html.twig',
                'id' => 'key_management',
            ])
    ;
};
