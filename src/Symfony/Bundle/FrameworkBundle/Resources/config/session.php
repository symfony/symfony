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

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\IdentityMarshaller;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MarshallingSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpKernel\EventListener\SessionListener;

return static function (ContainerConfigurator $container) {
    $container->parameters()->set('session.metadata.storage_key', '_sf2_meta');

    $container->services()
        ->set('session', Session::class)
            ->public()
            ->args([
                service('session.storage'),
                null, // AttributeBagInterface
                null, // FlashBagInterface
                [service('session_listener'), 'onSessionUsage'],
            ])
        ->alias(SessionInterface::class, 'session')
        ->alias(SessionStorageInterface::class, 'session.storage')
        ->alias(\SessionHandlerInterface::class, 'session.handler')

        ->set('session.storage.metadata_bag', MetadataBag::class)
            ->args([
                param('session.metadata.storage_key'),
                param('session.metadata.update_threshold'),
            ])

        ->set('session.storage.native', NativeSessionStorage::class)
            ->args([
                param('session.storage.options'),
                service('session.handler'),
                service('session.storage.metadata_bag'),
            ])

        ->set('session.storage.php_bridge', PhpBridgeSessionStorage::class)
            ->args([
                service('session.handler'),
                service('session.storage.metadata_bag'),
            ])

        ->set('session.flash_bag', FlashBag::class)
            ->factory([service('session'), 'getFlashBag'])
            ->deprecate('symfony/framework-bundle', '5.1', 'The "%service_id%" service is deprecated, use "$session->getFlashBag()" instead.')
        ->alias(FlashBagInterface::class, 'session.flash_bag')

        ->set('session.attribute_bag', AttributeBag::class)
            ->factory([service('session'), 'getBag'])
            ->args(['attributes'])
            ->deprecate('symfony/framework-bundle', '5.1', 'The "%service_id%" service is deprecated, use "$session->getAttributeBag()" instead.')

        ->set('session.storage.mock_file', MockFileSessionStorage::class)
            ->args([
                param('kernel.cache_dir').'/sessions',
                'MOCKSESSID',
                service('session.storage.metadata_bag'),
            ])

        ->set('session.handler.native_file', StrictSessionHandler::class)
            ->args([
                inline_service(NativeFileSessionHandler::class)
                    ->args([param('session.save_path')]),
            ])

        ->set('session.abstract_handler', AbstractSessionHandler::class)
            ->factory([SessionHandlerFactory::class, 'createHandler'])
            ->args([abstract_arg('A string or a connection object')])

        ->set('session_listener', SessionListener::class)
            ->args([
                service_locator([
                    'session' => service('session')->ignoreOnInvalid(),
                    'initialized_session' => service('session')->ignoreOnUninitialized(),
                    'logger' => service('logger')->ignoreOnInvalid(),
                    'session_collector' => service('data_collector.request.session_collector')->ignoreOnInvalid(),
                ]),
                param('kernel.debug'),
            ])
            ->tag('kernel.event_subscriber')

        // for BC
        ->alias('session.storage.filesystem', 'session.storage.mock_file')

        ->set('session.marshaller', IdentityMarshaller::class)

        ->set('session.marshalling_handler', MarshallingSessionHandler::class)
            ->decorate('session.handler')
            ->args([
                service('session.marshalling_handler.inner'),
                service('session.marshaller'),
            ])
    ;
};
