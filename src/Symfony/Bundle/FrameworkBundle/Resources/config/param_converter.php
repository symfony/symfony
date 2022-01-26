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

use Symfony\Component\HttpKernel\Controller\ParamConverter\DateTimeParamConverter;
use Symfony\Component\HttpKernel\Controller\ParamConverter\ParamConverterManager;
use Symfony\Component\HttpKernel\EventListener\ParamConverterListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('param_converter.listener', ParamConverterListener::class)
            ->args([
                service('param_converter.manager'),
                true,
            ])
            ->tag('kernel.event_subscriber')

        ->set('param_converter.manager', ParamConverterManager::class)

        ->set('date_time_param_converter', DateTimeParamConverter::class)
            ->tag('request.param_converter', [
                'converter' => 'datetime',
            ])
    ;
};
