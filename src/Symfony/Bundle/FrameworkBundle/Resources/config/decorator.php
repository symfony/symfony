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

use Symfony\Bundle\FrameworkBundle\EventListener\DecorateControllerListener;
use Symfony\Component\Decorator\CallableDecorator;
use Symfony\Component\Decorator\DecoratorInterface;
use Symfony\Component\Decorator\Resolver\DecoratorResolverInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('decorator.callable_decorator', CallableDecorator::class)
            ->args([
                service(DecoratorResolverInterface::class),
            ])

        ->alias(DecoratorInterface::class, 'decorator.callable_decorator')

        ->set('decorator.decorate_controller.listener', DecorateControllerListener::class)
            ->args([
                service('decorator.callable_decorator'),
            ])
            ->tag('kernel.event_subscriber')
    ;
};
