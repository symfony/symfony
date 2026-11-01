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

use Symfony\Component\RateLimiter\DataCollector\RateLimiterDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('rate_limiter.data_collector', RateLimiterDataCollector::class)
            ->args([
                service('request_stack'),
                service('profiler.is_disabled_state_checker')->nullOnInvalid(),
            ])
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/rate_limiter.html.twig',
                'id' => 'rate_limiter',
                'priority' => 270,
            ])
            ->tag('container.remove_if_missing', ['service' => 'profiler'])
    ;
};
