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

use Symfony\Component\HttpKernel\Debug\ErrorHandlerConfigurator;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\HttpKernel\EventListener\DebugHandlersListener;

return static function (ContainerConfigurator $container) {
    $container->parameters()->set('debug.error_handler.throw_at', -1);

    $container->services()
        ->set('debug.error_handler_configurator', ErrorHandlerConfigurator::class)
            ->public()
            ->args([
                service('monolog.logger.php')->nullOnInvalid(),
                null, // Log levels map for enabled error levels
                param('debug.error_handler.throw_at'),
                param('kernel.debug'),
                param('kernel.debug'),
                service('monolog.logger.deprecation')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'php'])

        ->set('debug.debug_handlers_listener', DebugHandlersListener::class)
            ->tag('kernel.event_subscriber')

        ->set('debug.file_link_formatter', FileLinkFormatter::class)
            ->args([param('debug.file_link_format')])

        ->alias(FileLinkFormatter::class, 'debug.file_link_formatter')
    ;
};
