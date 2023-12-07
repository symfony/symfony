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

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('feature_flag.routing_expression_language_function.is_enabled', \Closure::class)
            ->factory([\Closure::class, 'fromCallable'])
            ->args([
                [service('feature_flag.feature_checker'), 'isEnabled'],
            ])
            ->tag('routing.expression_language_function', ['function' => 'is_feature_enabled'])


        ->set('feature_flag.routing_expression_language_function.get_value', \Closure::class)
            ->factory([\Closure::class, 'fromCallable'])
            ->args([
                [service('feature_flag.feature_checker'), 'getValue'],
            ])
            ->tag('routing.expression_language_function', ['function' => 'get_feature_value'])

        ->get('feature_flag.feature_checker')
            ->tag('routing.condition_service', ['alias' => 'feature'])
    ;
};
