<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\SchemaListener\DataKeyStoreSchemaListener;
use Symfony\Component\KeyManagement\Bridge\Flysystem\DependencyInjection\RegisterFlysystemStoragesPass;
use Symfony\Component\KeyManagement\DependencyInjection\KeyManagementPass;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;

/**
 * Provides the KMS clients, the envelope encrypters and the data key store.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
#[RequiredBundle(ServicesBundle::class)]
class KeyManagementBundle extends AbstractBundle
{
    /**
     * Prefix of a "key_management.clients" DSN naming a client the application registered itself,
     * rather than one the factory registry has to build.
     */
    private const string SERVICE_SCHEME = 'service://';

    /**
     * Name the store's envelope encrypter answers to, both as an argument and as a target.
     */
    private const string STORE_TARGET = 'stored';

    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        // the container can drop the data collector this pass looks for, so run after it
        $container->addCompilerPass(new KeyManagementPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -16);

        if (class_exists(RegisterFlysystemStoragesPass::class)) {
            $container->addCompilerPass(new RegisterFlysystemStoragesPass());
        }

        if (class_exists(RegisterBlindIndexesPass::class)) {
            $container->addCompilerPass(new RegisterBlindIndexesPass());
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->acceptAndWrap(['string'], 'clients')
            ->canBeDisabled()
            ->children()
                ->scalarNode('default_client')
                    ->info('Name of the default client (must match an entry of "clients"); inferred when only one client is registered.')
                    ->defaultNull()
                ->end()
                ->arrayNode('clients', 'client')
                    ->info('Map of client name to DSN; "service://<id>" takes the client the application registered under that service id instead of building one from a DSN.')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static fn (string $dsn): array => ['default' => $dsn])
                    ->end()
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->always(static function (array $clients): array {
                            foreach ($clients as $name => $dsn) {
                                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $name)) {
                                    throw new InvalidArgumentException(\sprintf('The KMS client name "%s" is invalid: it must start with a letter or an underscore and contain only letters, digits, underscores, dots and dashes.', $name));
                                }
                            }

                            return $clients;
                        })
                    ->end()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                        ->validate()
                            ->ifTrue(static fn ($dsn): bool => !\is_string($dsn))
                            ->thenInvalid('The DSN of a KMS client must be a string, got %s.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('store')
                    ->info('Data key store: payloads then refer to a stored data key instead of carrying it, so the KMS is reached once per key and per process, and that key can be rewrapped under another provider without rewriting a payload.')
                    ->children()
                        ->scalarNode('connection')
                            ->info('Service id of the DBAL connection holding the table.')
                            ->defaultValue('doctrine.dbal.default_connection')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('client')
                            ->info('Name of the client wrapping the data keys this store creates (must match an entry of "clients").')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('key_id')
                            ->info('Master key wrapping the data keys this store creates (backend-specific: alias, ARN, key URL, ...).')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('table')
                            ->info('Name of the table holding the wrapped data keys.')
                            ->defaultValue('key_management_data_keys')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_age')
                            ->info('Seconds after which the current data key of a scope is retired in favour of a fresh one; the default of 30 days keeps what one key seals under the collision bound of the random 96-bit IV each payload carries.')
                            ->defaultValue(2592000)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * A client an application built itself is named in "clients" like any other, through a
     * "service://<id>" DSN. That scheme is resolved here, when the container is built, so it never
     * reaches the factory registry: an application that hides it behind an environment variable
     * gets an unsupported scheme at runtime instead, since nothing can be referenced from a value
     * that is unknown until then.
     *
     * What the scheme registers is a definition rather than an alias, and that is the point: the
     * client keeps the tag the console commands look it up by, the profiler decorates it, and it
     * gets an envelope encrypter and named argument aliases like a client built from a DSN. An
     * alias would carry no tag and be invisible to all three.
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/key_management.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/key_management_debug.php');
        }

        if (class_exists(Application::class)) {
            $configurator->import('Resources/config/console.php');
        }

        $container->registerForAutoconfiguration(KmsFactoryInterface::class)
            ->addTag('key_management.factory');

        $container->registerForAutoconfiguration(BlindIndex::class)
            ->addTag('key_management.blind_index');

        $clients = $config['clients'];

        $defaultName = $config['default_client'] ?? (1 === \count($clients) ? array_key_first($clients) : null);
        if (null !== $defaultName && !isset($clients[$defaultName])) {
            throw new LogicException(\sprintf('Default KMS client "%s" is not registered in "key_management.clients".', $defaultName));
        }

        foreach ($clients as $name => $dsn) {
            $serviceId = 'key_management.'.$name;

            $definition = $container->register($serviceId, EncrypterInterface::class)
                ->addTag('key_management.client', ['key' => $name]);

            if (str_starts_with($dsn, self::SERVICE_SCHEME)) {
                if ('' === $referencedId = substr($dsn, \strlen(self::SERVICE_SCHEME))) {
                    throw new InvalidArgumentException(\sprintf('The DSN of the KMS client "%s" must name a service id after "%s".', $name, self::SERVICE_SCHEME));
                }

                $definition->setFactory('current')
                    ->setArguments([[new Reference($referencedId)]]);
            } else {
                $definition->setFactory([new Reference('key_management.factory'), 'fromString'])
                    ->setArguments([$dsn]);
            }

            $envelopeId = 'key_management.envelope_encrypter.'.$name;
            $container->register($envelopeId, EnvelopeEncrypter::class)
                ->setArguments([new Reference($serviceId)]);

            foreach ([EncrypterInterface::class, DecrypterInterface::class, DataKeyGeneratorInterface::class] as $type) {
                $container->registerAliasForArgument($serviceId, $type, $name.'.kms', $name);
            }
            foreach ([EnvelopeEncrypterInterface::class, EnvelopeDecrypterInterface::class] as $type) {
                $container->registerAliasForArgument($envelopeId, $type, $name.'.envelope_encrypter', $name);
            }
        }

        if (null !== $defaultName) {
            $container->setAlias(EncrypterInterface::class, 'key_management.'.$defaultName);
            $container->setAlias(DecrypterInterface::class, 'key_management.'.$defaultName);
            $container->setAlias(DataKeyGeneratorInterface::class, 'key_management.'.$defaultName);
            $container->setAlias(EnvelopeEncrypterInterface::class, 'key_management.envelope_encrypter.'.$defaultName);
            $container->setAlias(EnvelopeDecrypterInterface::class, 'key_management.envelope_encrypter.'.$defaultName);
        }

        if (isset($config['store']['client'])) {
            $this->registerStore($config['store'], array_keys($clients), $defaultName, $container);
        }
    }

    /**
     * Configuring a store is what an application does to stop carrying a wrapped data key in every
     * payload, so the store-backed encrypter becomes the one the envelope interfaces resolve to.
     * Nothing is lost by that: it is given the default client's encrypter as a fallback, so it
     * reads the payloads written before it as well as the ones it writes. The per-client encrypters
     * stay reachable under their own name for whoever wants the other regime explicitly.
     *
     * The clients it can rewrap a data key under are the tagged ones rather than the configured
     * ones, so a client contributed by a bundle is a rewrap target as well. The one it wraps with
     * is still checked against the configuration, where a typo can be reported against a name.
     *
     * @param list<string> $clientNames
     */
    private function registerStore(array $config, array $clientNames, ?string $defaultName, ContainerBuilder $container): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/doctrine-dbal-key-management', DataKeyStore::class, ['symfony/key-management'])) {
            throw new LogicException('Configuring "key_management.store" requires the "symfony/doctrine-dbal-key-management" package. Try running "composer require symfony/doctrine-dbal-key-management".');
        }

        if (\in_array(self::STORE_TARGET, $clientNames, true)) {
            throw new LogicException(\sprintf('A KMS client cannot be named "%1$s" while a data key store is configured: the store registers the autowiring aliases of that name, which would leave the client of the same name unreachable. Rename the "%1$s" client.', self::STORE_TARGET));
        }

        if (!\in_array($config['client'], $clientNames, true)) {
            throw new LogicException(\sprintf('The KMS client "%s" set on "key_management.store" is not registered in "key_management.clients".', $config['client']));
        }

        $container->register('key_management.store', DataKeyStore::class)
            ->setArguments([
                new Reference($config['connection']),
                new ServiceLocatorArgument(new TaggedIteratorArgument('key_management.client', 'key', true)),
                $config['client'],
                $config['key_id'],
                $config['table'],
                32,
                $config['max_age'],
            ])
            ->addTag('kernel.reset', ['method' => 'forget']);

        $container->register('key_management.stored_envelope_encrypter', StoredEnvelopeEncrypter::class)
            ->setArguments([
                new Reference('key_management.store'),
                null !== $defaultName ? new Reference('key_management.envelope_encrypter.'.$defaultName) : null,
            ]);

        $container->setAlias(DataKeyStoreInterface::class, 'key_management.store');
        $container->setAlias(RewrappableDataKeyStoreInterface::class, 'key_management.store');
        $container->setAlias(EnvelopeEncrypterInterface::class, 'key_management.stored_envelope_encrypter');
        $container->setAlias(EnvelopeDecrypterInterface::class, 'key_management.stored_envelope_encrypter');

        foreach ([EnvelopeEncrypterInterface::class, EnvelopeDecrypterInterface::class] as $type) {
            $container->registerAliasForArgument('key_management.stored_envelope_encrypter', $type, self::STORE_TARGET.'.envelope_encrypter', self::STORE_TARGET);
        }

        // The store writes its own table, so Doctrine has to know about it: without this, an
        // application discovers it by having "doctrine:schema:update" ignore it and a migration
        // diff propose to drop it. DoctrineBundle registers the listeners of the Lock, Messenger,
        // Cache, Session and RememberMe stores itself and knows nothing of this one, so the store
        // that was just registered brings its own. Its base class is checked first because the
        // listener extends one the DoctrineBridge owns: loading it without that package around is
        // a fatal error, not a false answer from class_exists().
        if (
            ContainerBuilder::willBeAvailable('symfony/doctrine-bridge', AbstractSchemaListener::class, ['symfony/doctrine-orm-key-management'])
            && ContainerBuilder::willBeAvailable('symfony/doctrine-orm-key-management', DataKeyStoreSchemaListener::class, ['symfony/key-management'])
        ) {
            $container->register('key_management.store.schema_listener', DataKeyStoreSchemaListener::class)
                ->setArguments([new IteratorArgument([new Reference('key_management.store')])])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
        }
    }
}
