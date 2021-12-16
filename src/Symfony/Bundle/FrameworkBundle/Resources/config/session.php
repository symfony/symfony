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

use Symfony\Bundle\FrameworkBundle\Session\DeprecatedSessionFactory;
use Symfony\Bundle\FrameworkBundle\Session\ServiceSessionFactory;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\IdentityMarshaller;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MarshallingSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpKernel\EventListener\SessionListener;

return static function (ContainerConfigurator $container) {
    $container->parameters()->set('session.metadata.storage_key', '_sf2_meta');

    $container->services()
        ->set('.session.do-not-use', Session::class) // to be removed in 6.0
            ->factory([service('session.factory'), 'createSession'])
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
        ->set('session.storage.factory.service', ServiceSessionFactory::class)
            ->args([
                service('session.storage'),
            ])
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%service_id%" service is deprecated, use "session.storage.factory.native", "session.storage.factory.php_bridge" or "session.storage.factory.mock_file" instead.')

        ->set('.session.deprecated', SessionInterface::class) // to be removed in 6.0
            ->factory([inline_service(DeprecatedSessionFactory::class)->args([service('request_stack')]), 'getSession'])
        ->alias(SessionInterface::class, '.session.do-not-use')
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%alias_id%" and "SessionInterface" aliases are deprecated, use "$requestStack->getSession()" instead.')
        ->alias(SessionStorageInterface::class, 'session.storage')
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%alias_id%" alias is deprecated, use "session.storage.factory" instead.')
        ->alias(\SessionHandlerInterface::class, 'session.handler')

        ->set('session.storage.metadata_bag', MetadataBag::class)
            ->args([
                param('session.metadata.storage_key'),
                param('session.metadata.update_threshold'),
            ])
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%service_id%" service is deprecated, create your own "session.storage.factory" instead.')

        ->set('session.storage.native', NativeSessionStorage::class)
            ->args([
                param('session.storage.options'),
                service('session.handler'),
                service('session.storage.metadata_bag'),
            ])
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%service_id%" service is deprecated, use "session.storage.factory.native" instead.')

        ->set('session.storage.php_bridge', PhpBridgeSessionStorage::class)
            ->args([
                service('session.handler'),
                service('session.storage.metadata_bag'),
            ])
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%service_id%" service is deprecated, use "session.storage.factory.php_bridge" instead.')

        ->set('session.flash_bag', FlashBag::class)
            ->factory([service('.session.do-not-use'), 'getFlashBag'])
            ->deprecate('symfony/framework-bundle', '5.1', 'The "%service_id%" service is deprecated, use "$session->getFlashBag()" instead.')
        ->alias(FlashBagInterface::class, 'session.flash_bag')

        ->set('session.attribute_bag', AttributeBag::class)
            ->factory([service('.session.do-not-use'), 'getBag'])
            ->args(['attributes'])
            ->deprecate('symfony/framework-bundle', '5.1', 'The "%service_id%" service is deprecated, use "$session->getAttributeBag()" instead.')

        ->set('session.storage.mock_file', MockFileSessionStorage::class)
            ->args([
                param('kernel.cache_dir').'/sessions',
                'MOCKSESSID',
                service('session.storage.metadata_bag'),
            ])
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%service_id%" service is deprecated, use "session.storage.factory.mock_file" instead.')

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
                    'session_factory' => service('session.factory')->ignoreOnInvalid(),
                    'session' => service('.session.do-not-use')->ignoreOnInvalid(),
                    'initialized_session' => service('.session.do-not-use')->ignoreOnUninitialized(),
                    'logger' => service('logger')->ignoreOnInvalid(),
                    'session_collector' => service('data_collector.request.session_collector')->ignoreOnInvalid(),
                ]),
                param('kernel.debug'),
                param('session.storage.options'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('kernel.reset', ['method' => 'reset'])

        // for BC
        ->alias('session.storage.filesystem', 'session.storage.mock_file')
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%alias_id%" alias is deprecated, use "session.storage.factory.mock_file" instead.')

        ->set('session.marshaller', IdentityMarshaller::class)

        ->set('session.marshalling_handler', MarshallingSessionHandler::class)
            ->decorate('session.handler')
            ->args([
                service('session.marshalling_handler.inner'),
                service('session.marshaller'),
            ])
    ;
};
