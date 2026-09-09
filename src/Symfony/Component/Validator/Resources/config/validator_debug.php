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

use Symfony\Component\Validator\DataCollector\ValidatorDataCollector;
use Symfony\Component\Validator\Validator\TraceableValidator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('debug.validator', TraceableValidator::class)
            ->decorate('validator', null, 255)
            ->args([
                service('debug.validator.inner'),
                service('profiler.is_disabled_state_checker')->nullOnInvalid(),
            ])
            ->tag('kernel.reset', [
                'method' => 'reset',
            ])

        ->set('data_collector.validator', ValidatorDataCollector::class)
            ->args([
                service('debug.validator'),
            ])
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/validator.html.twig',
                'id' => 'validator',
                'priority' => 320,
            ])
    ;
};
