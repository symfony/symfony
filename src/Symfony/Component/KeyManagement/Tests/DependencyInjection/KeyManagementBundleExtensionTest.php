<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKmsFactory;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVaultFactory;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\SchemaListener\DataKeyStoreSchemaListener;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKmsFactory;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKmsFactory;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKmsFactory;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\Debug\TraceableDataKeyStore;
use Symfony\Component\KeyManagement\Debug\TraceableEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Component\KeyManagement\KeyManagementBundle;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class KeyManagementBundleExtensionTest extends TestCase
{
    #[DataProvider('provideCommandIds')]
    public function testCommandIsWiredWithTaggedLocator(string $serviceId)
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', []);
        });

        $this->assertTrue($container->hasDefinition($serviceId));

        $locator = $container->getDefinition($serviceId)->getArgument(0);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locator);

        $iterator = $locator->getTaggedIteratorArgument();
        $this->assertInstanceOf(TaggedIteratorArgument::class, $iterator);
        $this->assertSame('key_management.client', $iterator->getTag());
        $this->assertSame('key', $iterator->getIndexAttribute());
    }

    public static function provideCommandIds(): iterable
    {
        yield ['console.command.key_management_encrypt'];
        yield ['console.command.key_management_decrypt'];
        yield ['console.command.key_management_generate_data_key'];
    }

    #[DataProvider('provideHttpFactoryIds')]
    public function testAnHttpFactoryGetsTheApplicationHttpClient(string $serviceId)
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', []);
        });

        if (!$container->hasDefinition($serviceId)) {
            $this->markTestSkipped(\sprintf('"%s" is not installed.', $serviceId));
        }

        $client = $container->getDefinition($serviceId)->getArgument(0);
        $this->assertInstanceOf(Reference::class, $client);
        $this->assertSame('http_client', (string) $client);
        $this->assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $client->getInvalidBehavior());
    }

    public static function provideHttpFactoryIds(): iterable
    {
        yield 'hashicorp vault' => ['key_management.factory.hashicorp_vault_transit'];
        yield 'azure' => ['key_management.factory.azure_key_vault'];
        yield 'google cloud' => ['key_management.factory.google_cloud_kms'];
    }

    /**
     * The container drops each of these when its package is absent, which a typo in the class or in
     * the package name would turn into a service that is never there, or one that is always there
     * and fails on its first use.
     */
    #[DataProvider('provideOptionalServices')]
    public function testAnOptionalServiceNamesThePackageItNeeds(string $serviceId, array $expectedTag)
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            // the blind index listener is dropped when the application registers no index at all
            $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index');
            $container->loadFromExtension('key_management', []);
        });

        if (!$container->hasDefinition($serviceId)) {
            $this->markTestSkipped(\sprintf('"%s" is not installed.', $expectedTag['package'] ?? $expectedTag['class']));
        }

        $this->assertSame([$expectedTag], $container->getDefinition($serviceId)->getTag('container.remove_if_missing'));
    }

    public static function provideOptionalServices(): iterable
    {
        $parents = ['symfony/key-management'];

        yield 'flysystem' => ['key_management.factory.flysystem', ['class' => FlysystemKmsFactory::class, 'package' => 'symfony/flysystem-key-management', 'parent_packages' => $parents]];
        yield 'hashicorp vault' => ['key_management.factory.hashicorp_vault_transit', ['class' => TransitKmsFactory::class, 'package' => 'symfony/hashicorp-vault-key-management', 'parent_packages' => $parents]];
        yield 'aws' => ['key_management.factory.aws_kms', ['class' => AwsKmsFactory::class, 'package' => 'symfony/aws-key-management', 'parent_packages' => $parents]];
        yield 'azure' => ['key_management.factory.azure_key_vault', ['class' => AzureKeyVaultFactory::class, 'package' => 'symfony/azure-keyvault-key-management', 'parent_packages' => $parents]];
        yield 'google cloud' => ['key_management.factory.google_cloud_kms', ['class' => GoogleCloudKmsFactory::class, 'package' => 'symfony/google-cloud-key-management', 'parent_packages' => $parents]];
        yield 'blind index listener' => ['key_management.blind_index_listener', ['class' => BlindIndexListener::class, 'package' => 'symfony/doctrine-orm-key-management', 'parent_packages' => $parents]];
        yield 'envelope normalizer' => ['serializer.normalizer.key_management_envelope', ['class' => DenormalizerInterface::class]];
    }

    public function testTheRewrapCommandGetsTheStoreAndTheClients()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', []);
        });

        $arguments = $container->getDefinition('console.command.key_management_rewrap_data_keys')->getArguments();
        $this->assertSame('key_management.store', (string) $arguments[0]);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $arguments[1]);
    }

    /**
     * The host of a "...+fly://" DSN is looked up under the "key" attribute of the tag, the same
     * one the clients are indexed by. Left implicit, the index would be "flysystem", the last
     * segment of the tag name, and a service tagged as documented would only ever be found when
     * its id happens to equal the host.
     */
    public function testFlysystemFactoryIsWiredWithTaggedLocator()
    {
        if (!class_exists(FlysystemKmsFactory::class)) {
            $this->markTestSkipped('symfony/flysystem-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']);
        });

        $locator = $container->getDefinition('key_management.factory.flysystem')->getArgument(0);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locator);

        $iterator = $locator->getTaggedIteratorArgument();
        $this->assertSame('key_management.flysystem', $iterator->getTag());
        $this->assertSame('key', $iterator->getIndexAttribute());
    }

    /**
     * A client the application built itself is named in the configuration through a "service://"
     * DSN, and is registered as a definition rather than as an alias. What an alias would silently
     * drop is what is asserted here: the tag the console commands and the profiler find a client by,
     * the envelope encrypter, and the named argument aliases.
     */
    public function testClientCanBeAServiceTheApplicationRegistered()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.my_kms', \stdClass::class);
            $container->loadFromExtension('key_management', ['clients' => ['legacy' => 'service://app.my_kms']]);
        });

        $definition = $container->getDefinition('key_management.legacy');
        $this->assertSame('current', $definition->getFactory(), 'the client is taken as it is instead of being built from a DSN.');
        $this->assertSame('app.my_kms', (string) $definition->getArgument(0)[0]);
        $this->assertSame([['key' => 'legacy']], $definition->getTag('key_management.client'));

        $this->assertSame('key_management.legacy', (string) $container->getDefinition('key_management.envelope_encrypter.legacy')->getArgument(0));
        $this->assertSame('key_management.legacy', (string) $container->getAlias(EncrypterInterface::class.' $legacyKms'));
        $this->assertSame('key_management.legacy', (string) $container->getAlias(EncrypterInterface::class), 'the only client registered is the default one, wherever it comes from.');
    }

    public function testClientAsAServiceRequiresAServiceId()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The DSN of the KMS client "legacy" must name a service id after "service://".');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['legacy' => 'service://']]);
        });
    }

    /**
     * The scheme is resolved when the container is built, so a DSN whose value is unknown until
     * runtime is handed to the factory registry whatever it holds: an application that puts
     * "service://" in an environment variable gets an unsupported scheme, not a reference.
     */
    public function testClientFromAnEnvVarIsAlwaysBuiltFromADsn()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => '%env(KMS_DSN)%']]);
        });

        $factory = $container->getDefinition('key_management.app')->getFactory();
        $this->assertSame('key_management.factory', (string) $factory[0]);
        $this->assertSame('fromString', $factory[1]);
    }

    public function testFactoryTheApplicationRegistersIsAutoconfigured()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(KmsFactoryInterface::class, $autoconfigured);
        $this->assertSame(['key_management.factory' => [[]]], $autoconfigured[KmsFactoryInterface::class]->getTags(), 'a backend the application brings extends the schemes a DSN can use without being tagged by hand.');
    }

    public function testStoreConfigCreatesTheStoreAndItsEncrypter()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=AAAA'],
                'store' => [
                    'connection' => 'app.dbal',
                    'client' => 'app',
                    'key_id' => 'alias/app-key',
                    'table' => 'deks',
                    'max_age' => 3600,
                ],
            ]);
        });

        $this->assertTrue($container->hasDefinition('key_management.store'));
        $arguments = $container->getDefinition('key_management.store')->getArguments();
        $this->assertSame('app.dbal', (string) $arguments[0]);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $arguments[1]);
        $this->assertSame('key_management.client', $arguments[1]->getTaggedIteratorArgument()->getTag(), 'a data key can be rewrapped under any tagged client, not only under a configured one.');
        $this->assertSame('key', $arguments[1]->getTaggedIteratorArgument()->getIndexAttribute());
        $this->assertSame('app', $arguments[2]);
        $this->assertSame('alias/app-key', $arguments[3]);
        $this->assertSame('deks', $arguments[4]);
        $this->assertSame(3600, $arguments[6]);
        $this->assertSame([['method' => 'forget']], $container->getDefinition('key_management.store')->getTag('kernel.reset'), 'the retained plaintexts must not survive a unit of work in a long-running process.');

        $encrypter = $container->getDefinition('key_management.stored_envelope_encrypter')->getArguments();
        $this->assertSame('key_management.store', (string) $encrypter[0]);
        $this->assertSame('key_management.envelope_encrypter.app', (string) $encrypter[1], 'the default client provides the fallback that reads self-contained envelopes.');
    }

    /**
     * The store writes a table of its own, so Doctrine has to be told about it or a schema update
     * ignores it and a migration diff proposes to drop it.
     */
    public function testStoreBringsTheListenerThatPutsItsTableInTheSchema()
    {
        if (!class_exists(AbstractSchemaListener::class) || !class_exists(DataKeyStoreSchemaListener::class)) {
            $this->markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'alias/app-key'],
            ]);
        });

        $definition = $container->getDefinition('key_management.store.schema_listener');

        $this->assertSame(DataKeyStoreSchemaListener::class, $definition->getClass());
        $this->assertSame([['event' => 'postGenerateSchema']], $definition->getTag('doctrine.event_listener'));
        $this->assertSame(['key_management.store'], array_map(strval(...), $definition->getArgument(0)->getValues()));
    }

    /**
     * A store that seals payloads under one key forever is what the default must not produce, so
     * the configuration carries the age the store itself would have applied.
     */
    public function testStoreRotatesOnTheDefaultAgeWhenTheConfigurationIsSilent()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'alias/app-key'],
            ]);
        });

        $this->assertSame(DataKeyStore::DEFAULT_MAX_AGE_SECONDS, $container->getDefinition('key_management.store')->getArgument(6));
    }

    public function testWithoutAStoreRegistersNoSchemaListener()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $this->assertFalse($container->hasDefinition('key_management.store.schema_listener'));
    }

    public function testBringsTheListenerFillingTheBlindIndexColumns()
    {
        if (!class_exists(BlindIndexListener::class)) {
            $this->markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.email_index', BlindIndex::class)->addTag('key_management.blind_index');
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $definition = $container->getDefinition('key_management.blind_index_listener');

        $this->assertSame(BlindIndexListener::class, $definition->getClass());
        $this->assertSame([['event' => 'onFlush']], $definition->getTag('doctrine.event_listener'));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0), 'the pass hands the listener the blind indexes of the application.');
        $this->assertSame([['key_management.blind_index' => [[]]]], array_map(
            static fn (ChildDefinition $child): array => $child->getTags(),
            array_values(array_intersect_key($container->getAutoconfiguredInstanceof(), [BlindIndex::class => true])),
        ), 'a blind index the application registers is found by the listener without being tagged by hand.');
    }

    public function testTheBlindIndexListenerGoesWhenNoIndexIsRegistered()
    {
        if (!class_exists(BlindIndexListener::class)) {
            $this->markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $this->assertFalse($container->hasDefinition('key_management.blind_index_listener'));
    }

    public function testStoreIsWhatTheEnvelopeInterfacesResolveTo()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'alias/app-key'],
            ]);
        });

        $this->assertSame('key_management.store', (string) $container->getAlias(DataKeyStoreInterface::class));
        $this->assertSame('key_management.store', (string) $container->getAlias(RewrappableDataKeyStoreInterface::class));
        $this->assertSame('key_management.stored_envelope_encrypter', (string) $container->getAlias(EnvelopeEncrypterInterface::class), 'configuring a store is what makes it the encrypter the application gets.');
        $this->assertSame('key_management.stored_envelope_encrypter', (string) $container->getAlias(EnvelopeDecrypterInterface::class));
        $this->assertSame('key_management.envelope_encrypter.app', (string) $container->getAlias(EnvelopeEncrypterInterface::class.' $appEnvelopeEncrypter'), 'the per-client encrypter stays reachable under its own name.');
        $this->assertSame('key_management.stored_envelope_encrypter', (string) $container->getAlias(EnvelopeEncrypterInterface::class.' $storedEnvelopeEncrypter'));
        $this->assertTrue($container->hasAlias('.'.EnvelopeEncrypterInterface::class.' $stored'), "the store is reachable through #[Target('stored')] like any client.");
        $this->assertTrue($container->hasAlias('.'.EnvelopeDecrypterInterface::class.' $stored'));
    }

    /**
     * The store registers the autowiring aliases of the name "stored", and a client of that name
     * computes the very same ids, so the two would silently overwrite each other.
     */
    public function testAClientCannotBeNamedAfterTheStore()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A KMS client cannot be named "stored" while a data key store is configured');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['stored' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'stored', 'key_id' => 'k'],
            ]);
        });
    }

    public function testProfilerTracesTheClientsAndTheirEnvelopeEncrypters()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('profiler', \stdClass::class);
            $container->register('app.kms', \stdClass::class)->addTag('key_management.client', ['key' => 'tagged']);
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        }, true);

        $this->assertTrue($container->hasDefinition('key_management.data_collector'));
        $this->assertSame(['tagged', 'app'], $container->getDefinition('key_management.data_collector')->getArgument(0), 'a client the application tags itself is traced like a configured one.');

        $client = $container->getDefinition('debug.key_management.app');
        $this->assertSame([TraceableKms::class, 'wrap'], $client->getFactory(), 'the decorator mirrors the capabilities of the client it wraps rather than claiming them all.');
        $this->assertSame('key_management.app', $client->getDecoratedService()[0]);
        $this->assertSame('app', $client->getArgument(2));

        $encrypter = $container->getDefinition('debug.key_management.envelope_encrypter.app');
        $this->assertSame(TraceableEnvelopeEncrypter::class, $encrypter->getClass());
        $this->assertSame('key_management.envelope_encrypter.app', $encrypter->getDecoratedService()[0]);

        $this->assertSame('app.kms', $container->getDefinition('debug.app.kms')->getDecoratedService()[0]);
        $this->assertFalse($container->hasDefinition('debug.key_management.envelope_encrypter.tagged'), 'a tagged client has no envelope encrypter of its own to trace.');
    }

    public function testProfilerLeavesTheServicesAloneWhenItIsNotThere()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        }, true);

        $this->assertFalse($container->hasDefinition('key_management.data_collector'), 'the collector goes when no profiler collects it.');
        $this->assertFalse($container->hasDefinition('debug.key_management.app'));
    }

    public function testProfilerLeavesTheServicesAloneOutsideDebugMode()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('profiler', \stdClass::class);
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $this->assertFalse($container->hasDefinition('key_management.data_collector'));
        $this->assertFalse($container->hasDefinition('debug.key_management.app'));
    }

    public function testProfilerWrapsTheDataKeyStoreInsteadOfDecoratingIt()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('profiler', \stdClass::class);
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'alias/app-key'],
            ]);
        }, true);

        $traced = $container->getDefinition('debug.key_management.store');
        $this->assertSame(TraceableDataKeyStore::class, $traced->getClass());
        $this->assertNull($traced->getDecoratedService(), 'decorating would move the "kernel.reset" tag onto a decorator with no forget() to offer, and the retained plaintexts would survive a unit of work.');
        $this->assertSame([['method' => 'forget']], $container->getDefinition('key_management.store')->getTag('kernel.reset'));

        $this->assertSame('debug.key_management.store', (string) $container->getAlias(DataKeyStoreInterface::class));
        $this->assertSame('key_management.store', (string) $container->getAlias(RewrappableDataKeyStoreInterface::class), 'the rewrapping half keeps resolving to the store itself.');
        $this->assertSame('debug.key_management.store', (string) $container->getDefinition('key_management.stored_envelope_encrypter')->getArgument(0));
        $this->assertSame('key_management.stored_envelope_encrypter', $container->getDefinition('debug.key_management.stored_envelope_encrypter')->getDecoratedService()[0]);
    }

    /**
     * The fallback reading self-contained envelopes is the default client's envelope encrypter,
     * which the pass decorates as well. Reached through its decorator, one read would be recorded
     * twice, so the stored encrypter is given the decorated service itself.
     */
    public function testProfilerHandsTheStoredEncrypterAFallbackThatIsNotTracedTwice()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('profiler', \stdClass::class);
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'alias/app-key'],
            ]);
        }, true);

        $this->assertSame('debug.key_management.envelope_encrypter.app.inner', (string) $container->getDefinition('key_management.stored_envelope_encrypter')->getArgument(1));
    }

    public function testWithoutAStoreKeepsTheSelfContainedEncrypter()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $this->assertSame('key_management.envelope_encrypter.app', (string) $container->getAlias(EnvelopeEncrypterInterface::class));
        $this->assertFalse($container->hasAlias(DataKeyStoreInterface::class));
    }

    public function testStoreRejectsAClientThatIsNotRegistered()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The KMS client "gone" set on "key_management.store" is not registered');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=AAAA'],
                'store' => ['connection' => 'app.dbal', 'client' => 'gone', 'key_id' => 'k'],
            ]);
        });
    }

    /**
     * A store names the client wrapping its data keys, so a store configured without any client is
     * a configuration that cannot work.
     */
    public function testStoreWithoutAnyClientIsRefused()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The KMS client "app" set on "key_management.store" is not registered');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'k'],
            ]);
        });
    }

    public function testDefaultClientWithoutAnyClientIsRefused()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Default KMS client "app" is not registered in "key_management.clients".');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['default_client' => 'app']);
        });
    }

    /**
     * Enabling the bundle without configuring a client stays valid: the factories, the commands and
     * the blind index listener are what an application registering its clients as services of its
     * own uses, and it gets no default client since it declared none.
     */
    public function testWithoutAnyClientRegistersTheFactoriesAndNoDefault()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', []);
        });

        $this->assertTrue($container->hasDefinition('key_management.factory'));
        $this->assertFalse($container->hasAlias(EncrypterInterface::class));
    }

    public function testClientsConfigCreatesTaggedServicesAndAliases()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'app' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'vault' => 'hashicorp-vault-transit://t@vault.local:8200/v1/',
                ],
                'default_client' => 'app',
            ]);
        });

        $this->assertTrue($container->hasDefinition('key_management.app'));
        $this->assertTrue($container->hasDefinition('key_management.vault'));
        $this->assertTrue($container->hasDefinition('key_management.envelope_encrypter.app'));
        $this->assertTrue($container->hasDefinition('key_management.envelope_encrypter.vault'));

        $this->assertSame([['key' => 'app']], $container->getDefinition('key_management.app')->getTag('key_management.client'));
        $this->assertSame('key_management.app', (string) $container->getAlias(EncrypterInterface::class));
        $this->assertSame('key_management.envelope_encrypter.app', (string) $container->getAlias(EnvelopeEncrypterInterface::class));
    }

    public function testSingleClientBecomesDefaultAutomatically()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['only' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $this->assertSame('key_management.only', (string) $container->getAlias(EncrypterInterface::class));
    }

    public function testScalarShorthandIsExpandedToDefaultClient()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->setExtensionConfig('key_management', ['sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=']);
        });

        $this->assertTrue($container->hasDefinition('key_management.default'));
        $this->assertSame('key_management.default', (string) $container->getAlias(EncrypterInterface::class));
    }

    public function testClientsAreReachableViaTargetAttribute()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'app' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'vault' => 'hashicorp-vault-transit://t@vault.local:8200/v1/',
                ],
                'default_client' => 'app',
            ]);
        });

        // `#[Target('vault')] EncrypterInterface $foo` resolves through this alias chain.
        foreach ([EncrypterInterface::class, DecrypterInterface::class, DataKeyGeneratorInterface::class, EnvelopeEncrypterInterface::class, EnvelopeDecrypterInterface::class] as $type) {
            $this->assertTrue($container->hasAlias('.'.$type.' $vault'), $type);
        }

        // The named-argument fallback, which the attribute deprecates, names the role of the service.
        $this->assertSame('key_management.vault', (string) $container->getAlias(EncrypterInterface::class.' $vaultKms'));
        $this->assertSame('key_management.vault', (string) $container->getAlias(DataKeyGeneratorInterface::class.' $vaultKms'));
        $this->assertSame('key_management.envelope_encrypter.vault', (string) $container->getAlias(EnvelopeEncrypterInterface::class.' $vaultEnvelopeEncrypter'));
    }

    /**
     * Every backend generates data keys, and a blind index is built around one, so an application
     * registering its own indexes autowires the generator like the rest.
     */
    public function testTheDefaultClientIsTheDataKeyGeneratorToo()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => ['app' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=']]);
        });

        $this->assertSame('key_management.app', (string) $container->getAlias(DataKeyGeneratorInterface::class));
    }

    public function testAwsBridgeIsWiredWhenInstalled()
    {
        if (!class_exists(AwsKmsFactory::class)) {
            $this->markTestSkipped('symfony/aws-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->setExtensionConfig('key_management', ['aws-kms://default?region=eu-west-1']);
        });

        $this->assertTrue($container->hasDefinition('key_management.default'));
        $this->assertTrue($container->hasDefinition('key_management.factory.aws_kms'));
    }

    private function createContainerFromClosure(\Closure $closure, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.debug' => $debug, 'kernel.project_dir' => __DIR__]));
        $bundle = new KeyManagementBundle();
        $bundle->build($container);
        $container->registerExtension($bundle->getContainerExtension());
        new ClosureLoader($container)->load($closure);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
