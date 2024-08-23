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

use Symfony\Bundle\FrameworkBundle\EventListener\WrapControllerListener;
use Symfony\Component\CallableWrapper\CallableWrapper;
use Symfony\Component\CallableWrapper\CallableWrapperInterface;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolverInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('callable_wrapper', CallableWrapper::class)
            ->args([
                service(CallableWrapperResolverInterface::class),
            ])

        ->alias(CallableWrapperInterface::class, 'callable_wrapper')

        ->set('callable_wrapper.wrap_controller.listener', WrapControllerListener::class)
            ->args([
                service('callable_wrapper'),
            ])
            ->tag('kernel.event_subscriber')
    ;
};
