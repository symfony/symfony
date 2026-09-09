<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Mailer\EventListener\InMemoryPgpPublicKeyRepository;
use Symfony\Component\Mailer\EventListener\InMemorySmimeCertificateRepository;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Process\Process;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\Webhook\Controller\WebhookController;

/**
 * Provides the services that send emails.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(MessengerBundle::class, ignoreOnInvalid: true)]
class MailerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['dsn']) && $v['transports'])
                ->thenInvalid('"dsn" and "transports" cannot be used together.')
            ->end()
            ->children()
                ->scalarNode('message_bus')->defaultNull()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()
                ->scalarNode('dsn')->defaultNull()->end()
                ->arrayNode('transports', 'transport')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $dsn) => ['dsn' => $dsn])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->end()
                            ->scalarNode('rate_limiter')
                                ->defaultNull()
                                ->info('Rate limiter name used to limit the number of messages sent through this transport; when the limit is exceeded, sending fails with a RateLimitExceededException.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('envelope')
                    ->info('Mailer Envelope configuration')
                    ->children()
                        ->scalarNode('sender')->end()
                        ->arrayNode('recipients', 'recipient')
                            ->performNoDeepMerging()
                            ->acceptAndWrap(['string'])
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static fn ($v) => array_values(array_filter($v)))
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('allowed_recipients', 'allowed_recipient')
                            ->info('A list of regular expressions that allow recipients when "recipients" option is defined.')
                            ->example(['.*@example\.com'])
                            ->performNoDeepMerging()
                            ->acceptAndWrap(['string'])
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static fn ($v) => array_values(array_filter($v)))
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers', 'header')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->normalizeKeys(false)
                        ->acceptAndWrap(['string'], 'value')
                        ->beforeNormalization()
                            ->ifArray()
                            ->then(static fn ($v) => array_keys($v) !== ['value'] ? ['value' => $v] : $v)
                        ->end()
                        ->children()
                            ->variableNode('value')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tracking')
                    ->info('Default open/click tracking for every message that does not carry an explicit "X-Track" header; null keeps each provider\'s default. An "X-Track" entry in the "headers" option wins over this one.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('opens')->values([true, false, null])->defaultNull()->end()
                        ->enumNode('clicks')->values([true, false, null])->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('dkim_signer')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->info('DKIM signer configuration')
                    ->children()
                        ->scalarNode('key')
                            ->info('Key content, or path to key (in PEM format with the `file://` prefix)')
                            ->defaultValue('')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('domain')->defaultValue('')->end()
                        ->scalarNode('select')->defaultValue('')->end()
                        ->scalarNode('passphrase')
                            ->info('The private key passphrase')
                            ->defaultValue('')
                        ->end()
                        ->arrayNode('options', 'option')
                            ->performNoDeepMerging()
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->prototype('variable')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('smime_signer')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->info('S/MIME signer configuration')
                    ->children()
                        ->scalarNode('key')
                            ->info('Path to key (in PEM format)')
                            ->defaultValue('')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('certificate')
                            ->info('Path to certificate (in PEM format without the `file://` prefix)')
                            ->defaultValue('')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('passphrase')
                            ->info('The private key passphrase')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('extra_certificates')->defaultNull()->end()
                        ->integerNode('sign_options')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('smime_encrypter')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->info('S/MIME encrypter configuration')
                    ->validate()
                        ->ifTrue(static fn ($v) => $v['enabled'] && '' === $v['repository'] && !$v['certificates'])
                        ->thenInvalid('You must configure either "smime_encrypter.repository" or "smime_encrypter.certificates".')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn ($v) => '' !== $v['repository'] && $v['certificates'])
                        ->thenInvalid('You cannot use both "smime_encrypter.repository" and "smime_encrypter.certificates" at the same time.')
                    ->end()
                    ->children()
                        ->scalarNode('repository')
                            ->info('S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`.')
                            ->defaultValue('')
                        ->end()
                        ->arrayNode('certificates')
                            ->info('Recipient S/MIME certificates, as a map of email address to certificate file path. Cannot be used together with "repository".')
                            ->useAttributeAsKey('email')
                            ->scalarPrototype()->end()
                        ->end()
                        ->enumNode('on_missing_certificate')
                            ->info('Default behavior when a recipient has no S/MIME certificate: "send_unencrypted" (send the message unencrypted, deprecated since 8.2), "fail" (throw an exception), "encrypt" (encrypt for the recipients that have a certificate, the others receive an unreadable message), "skip" (encrypt for the recipients that have a certificate and drop the others from the envelope; note that "mailer.envelope.recipients" is applied afterwards and overrides that list). Can be overridden per message by setting the "X-SMime-Encrypt" header to one of these values.')
                            ->values(['send_unencrypted', 'fail', 'encrypt', 'skip'])
                            ->defaultValue('send_unencrypted')
                        ->end()
                        ->booleanNode('encrypt_for_sender')
                            ->info('Also encrypt for the sender, when a certificate is available for its address, so that the sender can read the messages it sent.')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('cipher')
                            ->info('A set of algorithms used to encrypt the message')
                            ->defaultNull()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static function ($v): int {
                                    $ciphers = self::getOpensslCiphers();

                                    if (!isset($ciphers[$v])) {
                                        throw new \InvalidArgumentException(\sprintf('"%s" is not a valid OPENSSL cipher.', $v));
                                    }

                                    return $ciphers[$v];
                                })
                            ->end()
                            ->validate()
                                ->ifTrue(static fn ($v) => null !== $v && ($ciphers = self::getOpensslCiphers()) && !\in_array($v, $ciphers, true))
                                ->thenInvalid('You must provide a valid cipher.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pgp_signer')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->info('PGP/MIME signer configuration')
                    ->validate()
                        ->ifTrue(static fn ($v) => $v['enabled'] && '' === $v['secret_key'])
                        ->thenInvalid('You must configure "pgp_signer.secret_key".')
                    ->end()
                    ->children()
                        ->scalarNode('secret_key')
                            ->info('Path to the secret key (ASCII armored format without the `file://` prefix)')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('public_key')
                            ->info('Path to the public key (ASCII armored format without the `file://` prefix)')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('passphrase')
                            ->info('The secret key passphrase')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('binary')
                            ->info('Path to the GnuPG binary')
                            ->defaultValue('gpg')
                        ->end()
                        ->enumNode('digest_algorithm')
                            ->info('The digest algorithm used to sign the message')
                            ->values(['SHA224', 'SHA256', 'SHA384', 'SHA512'])
                            ->defaultValue('SHA512')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pgp_encrypter')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->info('PGP/MIME encrypter configuration')
                    ->validate()
                        ->ifTrue(static fn ($v) => $v['enabled'] && '' === $v['repository'] && !$v['keys'])
                        ->thenInvalid('You must configure either "pgp_encrypter.repository" or "pgp_encrypter.keys".')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn ($v) => '' !== $v['repository'] && $v['keys'])
                        ->thenInvalid('You cannot use both "pgp_encrypter.repository" and "pgp_encrypter.keys" at the same time.')
                    ->end()
                    ->children()
                        ->scalarNode('repository')
                            ->info('Service or class implementing `Symfony\Component\Mailer\EventListener\PgpPublicKeyRepositoryInterface` to provide recipient PGP public keys.')
                            ->defaultValue('')
                        ->end()
                        ->arrayNode('keys')
                            ->info('Recipient PGP public keys, as a map of email address to public key file path. Cannot be used together with "repository".')
                            ->useAttributeAsKey('email')
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('binary')
                            ->info('Path to the GnuPG binary')
                            ->defaultValue('gpg')
                        ->end()
                        ->enumNode('cipher_algorithm')
                            ->info('The cipher algorithm used to encrypt the message')
                            ->values(['AES', 'AES192', 'AES256', 'TWOFISH', 'CAMELLIA128', 'CAMELLIA192', 'CAMELLIA256'])
                            ->defaultValue('AES256')
                        ->end()
                        ->floatNode('timeout')
                            ->info('Timeout in seconds for the GPG process (null for no timeout)')
                            ->defaultValue(60.0)
                        ->end()
                        ->booleanNode('hide_recipients')
                            ->info('Hide every recipient\'s key ID in the encrypted message (gpg --hidden-recipient). Recipients listed in the Bcc header are always hidden regardless of this option; set it to true to also hide To and Cc recipients.')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('on_missing_key')
                            ->info('Default behavior when a recipient has no PGP public key: "fail" (throw an exception), "encrypt" (encrypt for the recipients that have a key, the others receive an unreadable message), "skip" (encrypt for the recipients that have a key and drop the others from the envelope; note that "mailer.envelope.recipients" is applied afterwards and overrides that list). The message is never sent unencrypted. Can be overridden per message by setting the "X-Pgp-Encrypt" header to one of these values.')
                            ->values(['fail', 'encrypt', 'skip'])
                            ->defaultValue('fail')
                        ->end()
                        ->booleanNode('encrypt_for_sender')
                            ->info('Also encrypt for the sender, when a public key is available for its address, so that the sender can read the messages it sent.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/mailer.php');
        $configurator->import('Resources/config/mailer_transports.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/mailer_debug.php');
        }

        if (!class_exists(Application::class)) {
            $container->removeDefinition('console.command.mailer_test');
        }

        if (!$config['transports'] && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $transports = array_map(static function (array|string $transport): array {
            if (\is_array($transport)) {
                return $transport;
            }

            return ['dsn' => $transport];
        }, $transports);

        $container->getDefinition('mailer.transports')->setArgument(0, array_combine(array_keys($transports), array_column($transports, 'dsn')));

        $transportRateLimiterReferences = [];

        foreach ($transports as $name => $transport) {
            if ($transport['rate_limiter'] ?? null) {
                if (!interface_exists(LimiterInterface::class)) {
                    throw new LogicException('Rate limiter cannot be used within Mailer as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
                }

                $transportRateLimiterReferences[$name] = new Reference('limiter.'.$transport['rate_limiter']);
            }
        }

        if (!$transportRateLimiterReferences) {
            $container->removeDefinition('mailer.rate_limiter_locator');
        } else {
            $container->getDefinition('mailer.rate_limiter_locator')->replaceArgument(0, $transportRateLimiterReferences);
        }

        $mailer = $container->getDefinition('mailer.mailer');
        if (false === $messageBus = $config['message_bus']) {
            $mailer->replaceArgument(1, null);
        } else {
            $mailer->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        $classToServices = [
            Bridge\AhaSend\Transport\AhaSendTransportFactory::class => ['symfony/aha-send-mailer', 'mailer.transport_factory.ahasend'],
            Bridge\Azure\Transport\AzureTransportFactory::class => ['symfony/azure-mailer', 'mailer.transport_factory.azure'],
            Bridge\Brevo\Transport\BrevoTransportFactory::class => ['symfony/brevo-mailer', 'mailer.transport_factory.brevo'],
            Bridge\Cloudflare\Transport\CloudflareTransportFactory::class => ['symfony/cloudflare-mailer', 'mailer.transport_factory.cloudflare'],
            Bridge\Google\Transport\GmailTransportFactory::class => ['symfony/google-mailer', 'mailer.transport_factory.gmail'],
            Bridge\Infobip\Transport\InfobipTransportFactory::class => ['symfony/infobip-mailer', 'mailer.transport_factory.infobip'],
            Bridge\MailerSend\Transport\MailerSendTransportFactory::class => ['symfony/mailer-send-mailer', 'mailer.transport_factory.mailersend'],
            Bridge\Mailgun\Transport\MailgunTransportFactory::class => ['symfony/mailgun-mailer', 'mailer.transport_factory.mailgun'],
            Bridge\Mailjet\Transport\MailjetTransportFactory::class => ['symfony/mailjet-mailer', 'mailer.transport_factory.mailjet'],
            Bridge\MailKite\Transport\MailKiteTransportFactory::class => ['symfony/mail-kite-mailer', 'mailer.transport_factory.mailkite'],
            Bridge\Mailomat\Transport\MailomatTransportFactory::class => ['symfony/mailomat-mailer', 'mailer.transport_factory.mailomat'],
            Bridge\MailPace\Transport\MailPaceTransportFactory::class => ['symfony/mail-pace-mailer', 'mailer.transport_factory.mailpace'],
            Bridge\Mailchimp\Transport\MandrillTransportFactory::class => ['symfony/mailchimp-mailer', 'mailer.transport_factory.mailchimp'],
            Bridge\MicrosoftGraph\Transport\MicrosoftGraphTransportFactory::class => ['symfony/microsoft-graph-mailer', 'mailer.transport_factory.microsoftgraph'],
            Bridge\Postal\Transport\PostalTransportFactory::class => ['symfony/postal-mailer', 'mailer.transport_factory.postal'],
            Bridge\Postmark\Transport\PostmarkTransportFactory::class => ['symfony/postmark-mailer', 'mailer.transport_factory.postmark'],
            Bridge\PufferPost\Transport\PufferPostTransportFactory::class => ['symfony/puffer-post-mailer', 'mailer.transport_factory.pufferpost'],
            Bridge\Mailtrap\Transport\MailtrapTransportFactory::class => ['symfony/mailtrap-mailer', 'mailer.transport_factory.mailtrap'],
            Bridge\Resend\Transport\ResendTransportFactory::class => ['symfony/resend-mailer', 'mailer.transport_factory.resend'],
            Bridge\Scaleway\Transport\ScalewayTransportFactory::class => ['symfony/scaleway-mailer', 'mailer.transport_factory.scaleway'],
            Bridge\Sendgrid\Transport\SendgridTransportFactory::class => ['symfony/sendgrid-mailer', 'mailer.transport_factory.sendgrid'],
            Bridge\Amazon\Transport\SesTransportFactory::class => ['symfony/amazon-mailer', 'mailer.transport_factory.amazon'],
            Bridge\Sweego\Transport\SweegoTransportFactory::class => ['symfony/sweego-mailer', 'mailer.transport_factory.sweego'],
            Bridge\TurboSmtp\Transport\TurboSmtpTransportFactory::class => ['symfony/turbo-smtp-mailer', 'mailer.transport_factory.turbosmtp'],
        ];

        foreach ($classToServices as $class => [$package, $service]) {
            if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                $container->removeDefinition($service);
            }
        }

        $envelopeListener = $container->getDefinition('mailer.envelope_listener');
        $envelopeListener->setArgument(0, $config['envelope']['sender'] ?? null);
        $envelopeListener->setArgument(1, $config['envelope']['recipients'] ?? null);
        $envelopeListener->setArgument(2, $config['envelope']['allowed_recipients'] ?? []);

        $tracking = $config['tracking'];
        $hasTracking = null !== $tracking['opens'] || null !== $tracking['clicks'];

        if ($config['headers'] || $hasTracking) {
            $headers = new Definition(Headers::class);
            if ($hasTracking && !isset(array_change_key_case($config['headers'])['x-track'])) {
                $headers->addMethodCall('add', [new Definition(TrackingHeader::class, [$tracking['opens'], $tracking['clicks']])]);
            }
            foreach ($config['headers'] as $name => $data) {
                $value = $data['value'];
                if (\in_array(strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to'], true)) {
                    $value = (array) $value;
                }
                $headers->addMethodCall('addHeader', [$name, $value]);
            }
            $messageListener = $container->getDefinition('mailer.message_listener');
            $messageListener->setArgument(0, $headers);
        } else {
            $container->removeDefinition('mailer.message_listener');
        }

        if ($config['dkim_signer']['enabled']) {
            $dkimSigner = $container->getDefinition('mailer.dkim_signer');
            $dkimSigner->setArgument(0, $config['dkim_signer']['key']);
            $dkimSigner->setArgument(1, $config['dkim_signer']['domain']);
            $dkimSigner->setArgument(2, $config['dkim_signer']['select']);
            $dkimSigner->setArgument(3, $config['dkim_signer']['options']);
            $dkimSigner->setArgument(4, $config['dkim_signer']['passphrase']);
        } else {
            $container->removeDefinition('mailer.dkim_signer');
            $container->removeDefinition('mailer.dkim_signer.listener');
        }

        if ($config['smime_signer']['enabled']) {
            $smimeSigner = $container->getDefinition('mailer.smime_signer');
            $smimeSigner->setArgument(0, $config['smime_signer']['certificate']);
            $smimeSigner->setArgument(1, $config['smime_signer']['key']);
            $smimeSigner->setArgument(2, $config['smime_signer']['passphrase']);
            $smimeSigner->setArgument(3, $config['smime_signer']['extra_certificates']);
            $smimeSigner->setArgument(4, $config['smime_signer']['sign_options']);
        } else {
            $container->removeDefinition('mailer.smime_signer');
            $container->removeDefinition('mailer.smime_signer.listener');
        }

        if ($config['smime_encrypter']['enabled']) {
            if ($config['smime_encrypter']['certificates']) {
                $container->setDefinition('mailer.smime_encrypter.repository', new Definition(InMemorySmimeCertificateRepository::class, [$config['smime_encrypter']['certificates']]));
            } else {
                $container->setAlias('mailer.smime_encrypter.repository', $config['smime_encrypter']['repository']);
            }
            $container->setParameter('mailer.smime_encrypter.cipher', $config['smime_encrypter']['cipher']);
            $container->getDefinition('mailer.smime_encrypter.listener')
                ->setArgument(2, $config['smime_encrypter']['on_missing_certificate'])
                ->setArgument(3, $config['smime_encrypter']['encrypt_for_sender']);
        } else {
            $container->removeDefinition('mailer.smime_encrypter.listener');
        }

        if ($config['pgp_signer']['enabled']) {
            if (!class_exists(Process::class)) {
                throw new LogicException('PGP/MIME signed messages support cannot be enabled as the Process component is not installed. Try running "composer require symfony/process".');
            }
            $pgpSigner = $container->getDefinition('mailer.pgp_signer');
            $pgpSigner->setArgument(0, $config['pgp_signer']['secret_key']);
            $pgpSigner->setArgument(1, $config['pgp_signer']['public_key']);
            $pgpSigner->setArgument(2, $config['pgp_signer']['passphrase']);
            $pgpSigner->setArgument(3, [
                'binary' => $config['pgp_signer']['binary'],
                'digest_algorithm' => $config['pgp_signer']['digest_algorithm'],
            ]);
        } else {
            $container->removeDefinition('mailer.pgp_signer');
            $container->removeDefinition('mailer.pgp_signer.listener');
        }

        if ($config['pgp_encrypter']['enabled']) {
            if (!class_exists(Process::class)) {
                throw new LogicException('PGP/MIME encrypted messages support cannot be enabled as the Process component is not installed. Try running "composer require symfony/process".');
            }
            if ($config['pgp_encrypter']['keys']) {
                $container->setDefinition('mailer.pgp_encrypter.repository', new Definition(InMemoryPgpPublicKeyRepository::class, [$config['pgp_encrypter']['keys']]));
            } else {
                $container->setAlias('mailer.pgp_encrypter.repository', $config['pgp_encrypter']['repository']);
            }
            $pgpEncrypter = $container->getDefinition('mailer.pgp_encrypter');
            $pgpEncrypter->setArgument(0, [
                'binary' => $config['pgp_encrypter']['binary'],
                'cipher_algorithm' => $config['pgp_encrypter']['cipher_algorithm'],
                'timeout' => $config['pgp_encrypter']['timeout'],
                'hide_recipients' => $config['pgp_encrypter']['hide_recipients'],
            ]);
            $container->getDefinition('mailer.pgp_encrypter.listener')
                ->setArgument(2, $config['pgp_encrypter']['on_missing_key'])
                ->setArgument(3, $config['pgp_encrypter']['encrypt_for_sender']);
        } else {
            $container->removeDefinition('mailer.pgp_encrypter');
            $container->removeDefinition('mailer.pgp_encrypter.listener');
        }

        if (class_exists(WebhookController::class)) {
            $configurator->import('Resources/config/mailer_webhook.php');

            $debug = $container->getParameter('kernel.debug');
            $webhookRequestParsers = [
                Bridge\AhaSend\Webhook\AhaSendRequestParser::class => ['symfony/aha-send-mailer', 'mailer.webhook.request_parser.ahasend'],
                Bridge\Azure\Webhook\AzureRequestParser::class => ['symfony/azure-mailer', 'mailer.webhook.request_parser.azure'],
                Bridge\Brevo\Webhook\BrevoRequestParser::class => ['symfony/brevo-mailer', 'mailer.webhook.request_parser.brevo'],
                Bridge\MailerSend\Webhook\MailerSendRequestParser::class => ['symfony/mailer-send-mailer', 'mailer.webhook.request_parser.mailersend'],
                Bridge\Mailchimp\Webhook\MailchimpRequestParser::class => ['symfony/mailchimp-mailer', 'mailer.webhook.request_parser.mailchimp'],
                Bridge\Mailgun\Webhook\MailgunRequestParser::class => ['symfony/mailgun-mailer', 'mailer.webhook.request_parser.mailgun'],
                Bridge\Mailjet\Webhook\MailjetRequestParser::class => ['symfony/mailjet-mailer', 'mailer.webhook.request_parser.mailjet'],
                Bridge\Mailomat\Webhook\MailomatRequestParser::class => ['symfony/mailomat-mailer', 'mailer.webhook.request_parser.mailomat'],
                Bridge\Postmark\Webhook\PostmarkRequestParser::class => ['symfony/postmark-mailer', 'mailer.webhook.request_parser.postmark'],
                Bridge\Mailtrap\Webhook\MailtrapRequestParser::class => ['symfony/mailtrap-mailer', 'mailer.webhook.request_parser.mailtrap'],
                Bridge\Resend\Webhook\ResendRequestParser::class => ['symfony/resend-mailer', 'mailer.webhook.request_parser.resend'],
                Bridge\Scaleway\Webhook\ScalewayRequestParser::class => ['symfony/scaleway-mailer', 'mailer.webhook.request_parser.scaleway'],
                Bridge\Sendgrid\Webhook\SendgridRequestParser::class => ['symfony/sendgrid-mailer', 'mailer.webhook.request_parser.sendgrid'],
                Bridge\Sweego\Webhook\SweegoRequestParser::class => ['symfony/sweego-mailer', 'mailer.webhook.request_parser.sweego'],
                Bridge\TurboSmtp\Webhook\TurboSmtpRequestParser::class => ['symfony/turbo-smtp-mailer', 'mailer.webhook.request_parser.turbosmtp'],
            ];

            foreach ($webhookRequestParsers as $class => [$package, $service]) {
                if (!ContainerBuilder::willBeAvailable($package, $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                    $container->removeDefinition($service);
                } elseif ($debug && \defined($class.'::PROVIDER_IPS')) {
                    $container->getDefinition($service)->setArgument('$allowedIPs', [...$class::PROVIDER_IPS, '127.0.0.1']);
                }
            }
        }
    }

    /**
     * @return array<string, int> The values of the OPENSSL_CIPHER_* constants, keyed by their unprefixed name
     */
    private static function getOpensslCiphers(): array
    {
        $ciphers = [];

        foreach (get_defined_constants(true)['openssl'] ?? [] as $name => $value) {
            if (str_starts_with($name, 'OPENSSL_CIPHER_')) {
                $ciphers[substr($name, \strlen('OPENSSL_CIPHER_'))] = $value;
            }
        }

        return $ciphers;
    }
}
