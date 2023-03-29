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

use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\IdentityMarshaller;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MarshallingSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorageFactory;
use Symfony\Component\HttpKernel\EventListener\SessionListener;

return static function (ContainerConfigurator $container) {
    $container->parameters()->set('session.metadata.storage_key', '_sf2_meta');

    $container->services()
        ->set('session.factory', SessionFactory::class)
            ->args([
                service('request_stack'),
                service('session.storage.factory'),
                [service('session_listener'), 'onSessionUsage'],
            ])

        ->set('session.storage.factory.native', NativeSessionStorageFactory::class)
            ->args([
                param('session.storage.options'),
                service('session.handler'),
                inline_service(MetadataBag::class)
                    ->args([
                        param('session.metadata.storage_key'),
                        param('session.metadata.update_threshold'),
                    ]),
                false,
            ])
        ->set('session.storage.factory.php_bridge', PhpBridgeSessionStorageFactory::class)
            ->args([
                service('session.handler'),
                inline_service(MetadataBag::class)
                    ->args([
                        param('session.metadata.storage_key'),
                        param('session.metadata.update_threshold'),
                    ]),
                false,
            ])
        ->set('session.storage.factory.mock_file', MockFileSessionStorageFactory::class)
            ->args([
                param('kernel.cache_dir').'/sessions',
                'MOCKSESSID',
                inline_service(MetadataBag::class)
                    ->args([
                        param('session.metadata.storage_key'),
                        param('session.metadata.update_threshold'),
                    ]),
            ])

        ->alias(\SessionHandlerInterface::class, 'session.handler')

        ->set('session.handler.native', StrictSessionHandler::class)
            ->args([
                inline_service(\SessionHandler::class),
            ])

        ->set('session.handler.native_file', StrictSessionHandler::class)
            ->args([
                inline_service(NativeFileSessionHandler::class)
                    ->args([param('session.save_path')]),
            ])

        ->set('session.abstract_handler', AbstractSessionHandler::class)
            ->factory([SessionHandlerFactory::class, 'createHandler'])
            ->args([abstract_arg('A string or a connection object'), []])

        ->set('session_listener', SessionListener::class)
            ->args([
                service_locator([
                    'session_factory' => service('session.factory')->ignoreOnInvalid(),
                    'logger' => service('logger')->ignoreOnInvalid(),
                    'session_collector' => service('data_collector.request.session_collector')->ignoreOnInvalid(),
                ]),
                param('kernel.debug'),
                param('session.storage.options'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('session.marshaller', IdentityMarshaller::class)

        ->set('session.marshalling_handler', MarshallingSessionHandler::class)
            ->decorate('session.handler')
            ->args([
                service('session.marshalling_handler.inner'),
                service('session.marshaller'),
            ])
    ;
};
