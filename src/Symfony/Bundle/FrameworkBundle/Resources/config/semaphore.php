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

use Symfony\Component\Semaphore\SemaphoreFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('semaphore.factory.abstract', SemaphoreFactory::class)->abstract()
            ->args([abstract_arg('Store')])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'semaphore'])
    ;
};
