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
    $services = $container->services();

    $services->set('feature_flags.routing.provider', \Closure::class)
        ->factory([\Closure::class, 'fromCallable'])
        ->args([
            [service('feature_flags.feature_checker'), 'isEnabled'],
        ])
        ->tag('routing.expression_language_function', ['function' => 'isFeatureEnabled'])
    ;

    $services->get('feature_flags.feature_checker')
        ->tag('routing.condition_service', ['alias' => 'feature'])
    ;
};
