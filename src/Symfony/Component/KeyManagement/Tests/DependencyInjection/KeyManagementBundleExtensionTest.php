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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKmsFactory;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVaultFactory;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\SchemaListener\DataKeyStoreSchemaListener;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKmsFactory;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKmsFactory;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKmsFactory;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmSivEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\CiphertextCodec;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeRegistry;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKms;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKmsFactory;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\CompositeKms;
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
    private const string EVIDEN_GCM_SIV_BLOCK_CIPHER_MODE = '0x80000002';
    private const string OTHER_GCM_SIV_BLOCK_CIPHER_MODE = '0x80001234';
    private const string BELOW_KMIP_VENDOR_BLOCK_CIPHER_MODE = '0x7FFFFFFF';
    private const string ABOVE_KMIP_VENDOR_BLOCK_CIPHER_MODE = '0x90000000';

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
        yield 'aws' => ['key_management.factory.aws_kms'];
    }

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
        yield 'kmip registry' => ['key_management.kmip.encryption_scheme_registry', ['class' => KmipEncryptionSchemeRegistry::class, 'package' => 'symfony/kmip-key-management', 'parent_packages' => $parents]];
        yield 'kmip aes gcm siv template' => ['key_management.kmip.encryption_scheme.aes_gcm_siv_template', ['class' => AesGcmSivEncryptionScheme::class, 'package' => 'symfony/kmip-key-management', 'parent_packages' => $parents]];
        yield 'kmip' => ['key_management.factory.kmip', ['service' => 'key_management.kmip.encryption_scheme_registry', 'class' => KmipKmsFactory::class, 'package' => 'symfony/kmip-key-management', 'parent_packages' => $parents]];
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
        $this->assertSame([['method' => 'reset']], $container->getDefinition('key_management.factory.flysystem')->getTag('kernel.reset'));
    }

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

    public function testStoreNeverRotatesWhenTheMaxAgeIsNull()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=Q0VkRUNVTk5VTkRJVUVDU1U='],
                'store' => ['connection' => 'app.dbal', 'client' => 'app', 'key_id' => 'alias/app-key', 'max_age' => null],
            ]);
        });

        $this->assertNull($container->getDefinition('key_management.store')->getArgument(6));
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

    public function testStoreWrapsWithTheDefaultClientWhenToldNoOther()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => ['app' => 'sodium://?keys[app]=AAAA'],
                'store' => ['connection' => 'app.dbal', 'key_id' => 'alias/app-key'],
            ]);
        });

        $this->assertSame('app', $container->getDefinition('key_management.store')->getArgument(2));
    }

    public function testStoreWrapsWithACompositeClientLikeAnyOther()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'aws' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'azure' => 'sodium://?keys[backup]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'main' => ['members' => ['aws' => null, 'azure' => 'backup']],
                ],
                'default_client' => 'main',
                'store' => ['connection' => 'app.dbal', 'key_id' => 'alias/app-key'],
            ]);
        });

        $this->assertSame('main', $container->getDefinition('key_management.store')->getArgument(2));
        $this->assertSame('key_management.envelope_encrypter.main', (string) $container->getDefinition('key_management.stored_envelope_encrypter')->getArgument(1), 'the fallback reads the self-contained envelopes the composite client wrote.');
    }

    public function testStoreWithoutAClientNorADefaultOneIsRefused()
    {
        if (!class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('symfony/doctrine-dbal-key-management is not installed.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "key_management.store" needs a client to wrap its data keys with');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('app.dbal', \stdClass::class);
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'aws' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'azure' => 'sodium://?keys[backup]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                ],
                'store' => ['connection' => 'app.dbal', 'key_id' => 'alias/app-key'],
            ]);
        });
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

        foreach ([EncrypterInterface::class, DecrypterInterface::class, DataKeyGeneratorInterface::class, EnvelopeEncrypterInterface::class, EnvelopeDecrypterInterface::class] as $type) {
            $this->assertTrue($container->hasAlias('.'.$type.' $vault'), $type);
        }

        $this->assertSame('key_management.vault', (string) $container->getAlias(EncrypterInterface::class.' $vaultKms'));
        $this->assertSame('key_management.vault', (string) $container->getAlias(DataKeyGeneratorInterface::class.' $vaultKms'));
        $this->assertSame('key_management.envelope_encrypter.vault', (string) $container->getAlias(EnvelopeEncrypterInterface::class.' $vaultEnvelopeEncrypter'));
    }

    public function testAClientDeclaredByItsMembersIsACompositeOne()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'aws' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'azure' => 'sodium://?keys[backup]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'main' => ['members' => ['aws' => null, 'azure' => 'backup']],
                ],
                'default_client' => 'main',
            ]);
        });

        $definition = $container->getDefinition('key_management.main');
        $this->assertSame(CompositeKms::class, $definition->getClass());
        $this->assertInstanceOf(ServiceLocatorArgument::class, $definition->getArgument(0));
        $this->assertSame('key_management.client', $definition->getArgument(0)->getTaggedIteratorArgument()->getTag(), 'the members are resolved lazily through the locator of the tagged clients.');
        $this->assertSame('key', $definition->getArgument(0)->getTaggedIteratorArgument()->getIndexAttribute());
        $this->assertSame(['aws' => null, 'azure' => 'backup'], $definition->getArgument(1));
        $this->assertSame('logger', (string) $definition->getArgument(2), 'a member passed over is only ever reported to the logger.');
        $this->assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $definition->getArgument(2)->getInvalidBehavior());
        $this->assertSame([['channel' => 'key_management']], $definition->getTag('monolog.logger'));
        $this->assertSame([['key' => 'main']], $definition->getTag('key_management.client'), 'a composite client is a client like any other for the commands and the profiler.');

        $this->assertSame('key_management.main', (string) $container->getDefinition('key_management.envelope_encrypter.main')->getArgument(0));
        $this->assertSame('key_management.main', (string) $container->getAlias(EncrypterInterface::class), 'and it is the default when named so, like any other.');
        $this->assertSame('key_management.envelope_encrypter.main', (string) $container->getAlias(EnvelopeEncrypterInterface::class));

        foreach ([EncrypterInterface::class, DecrypterInterface::class, DataKeyGeneratorInterface::class, EnvelopeEncrypterInterface::class, EnvelopeDecrypterInterface::class] as $type) {
            $this->assertTrue($container->hasAlias('.'.$type.' $main'), $type);
            $this->assertTrue($container->hasAlias('.'.$type.' $aws'), 'each member stays reachable on its own.');
        }
    }

    public function testAMemberMustBeARegisteredClient()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The member "gcp" of the composite KMS client "main" is not registered in "key_management.clients".');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'aws' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'main' => ['members' => ['aws' => null, 'gcp' => 'backup']],
                ],
            ]);
        });
    }

    public function testAMemberCannotBeACompositeClientItself()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The member "inner" of the composite KMS client "outer" is a composite client itself');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', [
                'clients' => [
                    'aws' => 'sodium://?keys[main]=Q0VkRUNVTk5VTkRJVUVDU1U=',
                    'inner' => ['members' => ['aws' => null]],
                    'outer' => ['members' => ['inner' => null]],
                ],
                'default_client' => 'outer',
            ]);
        });
    }

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

    public function testKmipBridgeIsWiredWhenInstalled()
    {
        if (!class_exists(KmipKmsFactory::class)) {
            $this->markTestSkipped('symfony/kmip-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->setExtensionConfig('key_management', ['kmip://localhost?cert=/missing/cert&key=/missing/key&version=2.0']);
        });

        $this->assertTrue($container->hasDefinition('key_management.default'));
        $this->assertTrue($container->hasDefinition('key_management.factory.kmip'));
        $this->assertTrue($container->hasDefinition('key_management.kmip.encryption_scheme_registry'));
        $this->assertTrue($container->hasDefinition('key_management.kmip.encryption_scheme.aes_gcm'));
        $this->assertTrue($container->hasDefinition('key_management.kmip.encryption_scheme.chacha20_poly1305'));
        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme.aes_gcm_siv'));
        $this->assertTrue($container->getDefinition('key_management.kmip.encryption_scheme.aes_gcm_siv_template')->isAbstract());

        $registry = $container->getDefinition('key_management.kmip.encryption_scheme_registry');
        $iterator = $registry->getArgument(0);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $iterator);
        $this->assertSame('key_management.kmip.encryption_scheme', $iterator->getTag());

        $reference = $container->getDefinition('key_management.factory.kmip')->getArgument(0);
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('key_management.kmip.encryption_scheme_registry', (string) $reference);
    }

    public function testKmipAesGcmSivIsNotRegisteredWithoutAnExplicitMode()
    {
        if (!class_exists(AesGcmSivEncryptionScheme::class)) {
            $this->markTestSkipped('symfony/kmip-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', []);
        });

        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme.aes_gcm_siv'));
        $this->assertTrue($container->getDefinition('key_management.kmip.encryption_scheme.aes_gcm_siv_template')->isAbstract());
    }

    public function testKmipAesGcmIvLengthCanBeSelectedPerClientDsn()
    {
        if (!class_exists(AesGcmEncryptionScheme::class)) {
            $this->markTestSkipped('symfony/kmip-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['clients' => [
                'short' => 'kmip://short.example?cert=/missing/cert&key=/missing/key&version=2.0&iv_length=12',
                'long' => 'kmip://long.example?cert=/missing/cert&key=/missing/key&version=2.0&iv_length=16',
                'default' => 'kmip://default.example?cert=/missing/cert&key=/missing/key&version=2.0',
            ]]);
            $container->setAlias('test.kmip.short', 'key_management.short')->setPublic(true);
            $container->setAlias('test.kmip.long', 'key_management.long')->setPublic(true);
            $container->setAlias('test.kmip.default', 'key_management.default')->setPublic(true);
            $container->setAlias('test.kmip.registry', 'key_management.kmip.encryption_scheme_registry')->setPublic(true);
        }, optimize: true);

        foreach (['short' => 12, 'long' => 16, 'default' => 16] as $client => $ivLength) {
            $kms = $container->get('test.kmip.'.$client);
            $this->assertInstanceOf(KmipKms::class, $kms);
            $scheme = (new \ReflectionProperty($kms, 'encryptionScheme'))->getValue($kms);
            $this->assertInstanceOf(AesGcmEncryptionScheme::class, $scheme);
            $this->assertSame('aes-gcm', $scheme->name());
            $this->assertSame($ivLength, (new \ReflectionMethod($scheme, 'ivLength'))->invoke($scheme));
        }
        $registry = $container->get('test.kmip.registry');
        $this->assertSame(16, (new \ReflectionMethod($registry->get('aes-gcm'), 'ivLength'))->invoke($registry->get('aes-gcm')));
    }

    public function testKmipAesGcmSivModesCanBeConfiguredThroughTheBundle()
    {
        if (!class_exists(AesGcmSivEncryptionScheme::class)) {
            $this->markTestSkipped('symfony/kmip-key-management is not installed.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('key_management', ['kmip' => ['aes_gcm_siv_schemes' => [
                'aes-gcm-siv/eviden' => self::EVIDEN_GCM_SIV_BLOCK_CIPHER_MODE,
                'aes-gcm-siv/another' => self::OTHER_GCM_SIV_BLOCK_CIPHER_MODE,
            ]], 'clients' => [
                'eviden' => 'kmip://eviden.example?cert=/missing/cert&key=/missing/key&version=2.0&cipher=aes-gcm-siv/eviden',
                'another' => 'kmip://another.example?cert=/missing/cert&key=/missing/key&version=2.0&cipher=aes-gcm-siv/another',
            ]]);
            $container->setAlias('test.kmip.registry', 'key_management.kmip.encryption_scheme_registry')->setPublic(true);
            $container->setAlias('test.kmip.eviden', 'key_management.eviden')->setPublic(true);
            $container->setAlias('test.kmip.another', 'key_management.another')->setPublic(true);
        }, optimize: true);

        $registry = $container->get('test.kmip.registry');
        $this->assertInstanceOf(KmipEncryptionSchemeRegistry::class, $registry);
        $codec = new CiphertextCodec($registry);
        foreach (['aes-gcm-siv/eviden' => '80000002', 'aes-gcm-siv/another' => '80001234'] as $name => $mode) {
            $scheme = $registry->get($name);
            $this->assertInstanceOf(AesGcmSivEncryptionScheme::class, $scheme);
            $this->assertSame($name, $scheme->name());
            $this->assertSame('42002b0100000020'
                .'4200110500000004'.$mode.'00000000'
                .'42002805000000040000000300000000',
                bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 12)));
            [$decodedScheme, $decodedCiphertext] = $codec->decode($codec->encode($scheme, new Ciphertext('encrypted', 'key')), 'aad');
            $this->assertSame($scheme, $decodedScheme);
            $this->assertSame('encrypted', $decodedCiphertext->blob);
        }
        foreach (['eviden' => 'aes-gcm-siv/eviden', 'another' => 'aes-gcm-siv/another'] as $client => $schemeName) {
            $kms = $container->get('test.kmip.'.$client);
            $this->assertInstanceOf(KmipKms::class, $kms);
            $this->assertSame($registry->get($schemeName), (new \ReflectionProperty($kms, 'encryptionScheme'))->getValue($kms));
        }
    }

    #[DataProvider('invalidKmipAesGcmSivModes')]
    public function testKmipAesGcmSivRejectsModesOutsideTheVendorRange(mixed $mode)
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->createContainerFromClosure(static function (ContainerBuilder $container) use ($mode) {
            $container->loadFromExtension('key_management', ['kmip' => ['aes_gcm_siv_schemes' => ['aes-gcm-siv' => $mode]]]);
        });
    }

    public static function invalidKmipAesGcmSivModes(): iterable
    {
        yield 'below vendor extension range' => [self::BELOW_KMIP_VENDOR_BLOCK_CIPHER_MODE];
        yield 'above vendor extension range' => [self::ABOVE_KMIP_VENDOR_BLOCK_CIPHER_MODE];
        yield 'invalid hexadecimal' => ['0x8000000G'];
        yield 'unquoted decimal beyond 32-bit PHP integer range' => [2147483650.0];
    }

    #[DataProvider('invalidKmipAesGcmSivSchemeNames')]
    public function testKmipAesGcmSivSchemeNamesAreRejectedDuringConfiguration(string $name)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->createContainerFromClosure(static function (ContainerBuilder $container) use ($name) {
            $container->loadFromExtension('key_management', ['kmip' => ['aes_gcm_siv_schemes' => [$name => self::EVIDEN_GCM_SIV_BLOCK_CIPHER_MODE]]]);
        });
    }

    public static function invalidKmipAesGcmSivSchemeNames(): iterable
    {
        yield 'uppercase' => ['AES'];
        yield 'built-in AES-GCM' => ['aes-gcm'];
        yield 'built-in ChaCha20-Poly1305' => ['chacha20-poly1305'];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBundleLoadsWithoutKmipBridgeClasses()
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }
        foreach ($loaders as $loader) {
            spl_autoload_register(static function (string $class) use ($loader): void {
                if (!str_starts_with($class, 'Symfony\\Component\\KeyManagement\\Bridge\\Kmip\\')) {
                    $loader($class);
                }
            });
        }

        $this->assertFalse(class_exists(KmipKmsFactory::class));
        $this->assertFalse(class_exists(KmipEncryptionSchemeRegistry::class));

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->setExtensionConfig('key_management', ['openssl://?keys[app]='.Base64UrlSafe::encode(random_bytes(32))]);
            $container->setAlias('test.kms', 'key_management.default')->setPublic(true);
        }, optimize: true);

        $this->assertFalse($container->hasDefinition('key_management.factory.kmip'));
        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme_registry'));
        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme.aes_gcm'));
        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme.chacha20_poly1305'));
        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme.aes_gcm_siv'));
        $this->assertFalse($container->hasDefinition('key_management.kmip.encryption_scheme.aes_gcm_siv_template'));

        $kms = $container->get('test.kms');
        $this->assertSame('working', $kms->decrypt($kms->encrypt('app', 'working')));
    }

    private function createContainerFromClosure(\Closure $closure, bool $debug = false, bool $optimize = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.debug' => $debug, 'kernel.project_dir' => __DIR__]));
        $bundle = new KeyManagementBundle();
        $bundle->build($container);
        $container->registerExtension($bundle->getContainerExtension());
        new ClosureLoader($container)->load($closure);
        if (!$optimize) {
            $container->getCompilerPassConfig()->setOptimizationPasses([]);
            $container->getCompilerPassConfig()->setRemovingPasses([]);
            $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        }
        $container->compile();

        return $container;
    }
}
