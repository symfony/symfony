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

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Mailer\EventListener\DkimSignedMessageListener;
use Symfony\Component\Mailer\EventListener\EnvelopeListener;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\EventListener\MessengerTransportListener;
use Symfony\Component\Mailer\EventListener\PgpMimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\PgpMimeSignedMessageListener;
use Symfony\Component\Mailer\EventListener\SmimeEncryptedMessageListener;
use Symfony\Component\Mailer\EventListener\SmimeSignedMessageListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Mime\Crypto\SMimeSigner;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('mailer.mailer', Mailer::class)
            ->args([
                service('mailer.transports'),
                abstract_arg('message bus'),
                service('event_dispatcher')->ignoreOnInvalid(),
            ])
        ->alias('mailer', 'mailer.mailer')
        ->alias(MailerInterface::class, 'mailer.mailer')

        ->set('mailer.transports', Transports::class)
            ->factory([service('mailer.transport_factory'), 'fromStrings'])
            ->args([
                abstract_arg('transports'),
            ])

        ->set('mailer.transport_factory', Transport::class)
            ->args([
                tagged_iterator('mailer.transport_factory'),
                service('mailer.rate_limiter_locator')->nullOnInvalid(),
            ])

        ->alias('mailer.default_transport', 'mailer.transports')
        ->alias(TransportInterface::class, 'mailer.default_transport')

        ->set('mailer.messenger.message_handler', MessageHandler::class)
            ->args([
                service('mailer.transports'),
            ])
            ->tag('messenger.message_handler')

        ->set('mailer.envelope_listener', EnvelopeListener::class)
            ->args([
                abstract_arg('sender'),
                abstract_arg('recipients'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('mailer.message_listener', MessageListener::class)
            ->args([
                abstract_arg('headers'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('mailer.message_logger_listener', MessageLoggerListener::class)
            ->tag('kernel.event_subscriber')
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('mailer.messenger_transport_listener', MessengerTransportListener::class)
            ->tag('kernel.event_subscriber')

         ->set('mailer.dkim_signer', DkimSigner::class)
            ->args([
                abstract_arg('key'),
                abstract_arg('domain'),
                abstract_arg('select'),
                abstract_arg('options'),
                abstract_arg('passphrase'),
            ])

        ->set('mailer.smime_signer', SMimeSigner::class)
            ->args([
                abstract_arg('certificate'),
                abstract_arg('key'),
                abstract_arg('passphrase'),
                abstract_arg('extraCertificates'),
                abstract_arg('signOptions'),
            ])

        ->set('mailer.dkim_signer.listener', DkimSignedMessageListener::class)
            ->args([
                service('mailer.dkim_signer'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('mailer.smime_signer.listener', SmimeSignedMessageListener::class)
            ->args([
                service('mailer.smime_signer'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('mailer.smime_encrypter.listener', SmimeEncryptedMessageListener::class)
            ->args([
                service('mailer.smime_encrypter.repository'),
                param('mailer.smime_encrypter.cipher'),
                abstract_arg('on_missing_certificate'),
                abstract_arg('encrypt for sender'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'mailer'])

        ->set('mailer.pgp_signer', PgpSigner::class)
            ->args([
                abstract_arg('secret_key'),
                abstract_arg('public_key'),
                abstract_arg('passphrase'),
                abstract_arg('options'),
            ])

        ->set('mailer.pgp_signer.listener', PgpMimeSignedMessageListener::class)
            ->args([
                closure([service('mailer.pgp_signer'), 'sign']),
            ])
            ->tag('kernel.event_subscriber')

        ->set('mailer.pgp_encrypter', PgpEncrypter::class)
            ->args([
                abstract_arg('options'),
            ])

        ->set('mailer.pgp_encrypter.listener', PgpMimeEncryptedMessageListener::class)
            ->args([
                service('mailer.pgp_encrypter.repository'),
                closure([service('mailer.pgp_encrypter'), 'encrypt']),
                abstract_arg('on_missing_key'),
                abstract_arg('encrypt for sender'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'mailer'])

        ->set('console.command.mailer_test', MailerTestCommand::class)
            ->args([
                service('mailer.transports'),
            ])
            ->tag('console.command')

        ->set('mailer.rate_limiter_locator', ServiceLocator::class)
            ->args([[]])
            ->tag('container.service_locator')
    ;
};
