<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterEncryptedColumnsPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;
use Symfony\Component\KeyManagement\Bridge\Flysystem\DependencyInjection\RegisterFlysystemStoragesPass;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\DependencyInjection\KeyManagementPass;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\KeyManagementBundle;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\KeyManagement\Tests\Fixtures\Entity\Speaker;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

class KeyManagementBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_key_management_bundle_test';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['KMS_TEST_DSN']);
        new Filesystem()->remove($this->varDir);
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheDefaultClientEncryptsAndDecrypts()
    {
        $kernel = new TestKeyManagementKernel('test', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $kms = $container->get('test.kms');
        $this->assertInstanceOf(EncrypterInterface::class, $kms);
        $this->assertSame('secret', $kms->decrypt($kms->encrypt('app', 'secret')));

        $envelopeEncrypter = $container->get('test.envelope_encrypter');
        $this->assertInstanceOf(EnvelopeEncrypterInterface::class, $envelopeEncrypter);
        $this->assertSame('secret', $envelopeEncrypter->decrypt($envelopeEncrypter->encrypt('app', 'secret')));
    }

    #[RequiresPhpExtension('sodium')]
    public function testAClientReadsItsDsnFromAnEnvVar()
    {
        $_SERVER['KMS_TEST_DSN'] = 'sodium://default?keys[app]='.Base64UrlSafe::encode(random_bytes(32));

        $kernel = new TestKeyManagementKernel('env_dsn', false, $this->varDir);
        $kernel->boot();

        $kms = $kernel->getContainer()->get('test.kms');
        $this->assertSame('secret', $kms->decrypt($kms->encrypt('app', 'secret')));
    }

    #[RequiresPhpExtension('sodium')]
    public function testAServiceDsnTakesTheClientTheApplicationRegistered()
    {
        $kernel = new TestKeyManagementKernel('service', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $kms = $container->get('test.kms');
        $this->assertSame($container->get('app.kms'), $kms);
        $this->assertSame('secret', $kms->decrypt($kms->encrypt('app', 'secret')));
    }

    #[RequiresPhpExtension('sodium')]
    public function testAServiceDsnCannotBeReadFromAnEnvVar()
    {
        $_SERVER['KMS_TEST_DSN'] = 'service://app.kms';

        $kernel = new TestKeyManagementKernel('env_service', false, $this->varDir);
        $kernel->boot();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A "service://" KMS DSN is resolved when the container is compiled, so it cannot be read from an environment variable.');

        $kernel->getContainer()->get('test.kms');
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheClientsAreTracedInDebugMode()
    {
        $kernel = new TestKeyManagementKernel('debug', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $kms = $container->get('test.kms');
        $this->assertInstanceOf(TraceableKms::class, $kms);
        $this->assertSame('secret', $kms->decrypt($kms->encrypt('app', 'secret')));

        $this->assertInstanceOf(KeyManagementDataCollector::class, $container->get('test.data_collector'));
    }

    #[RequiresPhpExtension('sodium')]
    public function testAStoredEncrypterReadingASelfContainedEnvelopeIsRecordedOnce()
    {
        if (!class_exists(DriverManager::class) || !class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('store', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $envelope = $container->get('test.envelope_encrypter')->encrypt('app', 'secret');
        $this->assertSame('secret', $container->get('test.stored_envelope_encrypter')->decrypt($envelope));

        $collector = $container->get('test.data_collector');
        $collector->lateCollect();
        $services = $collector->getServices()[KeyManagementDataCollector::LAYER_ENVELOPE];

        $this->assertSame(2, $collector->getEnvelopeCallCount(), 'the write and the read, not the fallback the read went through.');
        $this->assertSame(['decrypt' => 1], $services['stored']['operations']);
        $this->assertSame(['encrypt' => 1], $services['default']['operations']);
    }

    #[RequiresPhpExtension('sodium')]
    #[RequiresPhpExtension('pdo_sqlite')]
    #[TestWith(['read'])]
    #[TestWith(['write'])]
    #[TestWith(['rewrap'])]
    public function testAnUnrelatedClientCannotSupplyAStoredDataKey(string $operation)
    {
        if (!class_exists(DriverManager::class) || !class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('store_clients', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();
        $store = $container->get('test.store');
        $store->createTable();
        $reference = $store->current('user.email')->reference;
        $key = str_repeat('a', 32);
        $publicKey = sodium_crypto_box_publickey(sodium_crypto_box_seed_keypair(str_repeat('p', 32)));
        $connection = $container->get('test.dbal');
        // bound under the secret of the store on purpose, so that only the client check refuses the row
        $connection->update(DataKeyStore::DEFAULT_TABLE, [
            'client' => 'public',
            'key_material' => sodium_crypto_box_seal($key, $publicKey),
            'binding' => StoredDataKey::bindingFor($reference, 'user.email', $key, 'kernel-secret'),
        ], ['id' => $reference], ['id' => ParameterType::BINARY, 'key_material' => ParameterType::BINARY, 'binding' => ParameterType::BINARY]);
        $store->forget();

        if ('rewrap' === $operation) {
            $tester = new CommandTester($container->get('test.rewrap'));
            $this->assertSame(Command::FAILURE, $tester->execute(['--to' => 'default', '--key-id' => 'app']));
            $this->assertSame('public', $connection->fetchOne('SELECT client FROM key_management_data_keys'));

            return;
        }

        $this->expectException(LogicException::class);
        'read' === $operation ? $store->get($reference) : $store->current('user.email');
    }

    #[RequiresPhpExtension('sodium')]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testAnExplicitlyTrustedClientCanRewrapStoredKeys()
    {
        if (!class_exists(DriverManager::class) || !class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('trusted_store_clients', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();
        $store = $container->get('test.store');
        $store->createTable();
        $encrypter = $container->get('test.stored_envelope_encrypter');
        $envelope = $encrypter->encrypt('user.email', 'secret');
        $tester = new CommandTester($container->get('test.rewrap'));
        $tester->execute(['--to' => 'legacy', '--key-id' => 'app']);
        $tester->assertCommandIsSuccessful();
        $store->forget();

        $this->assertSame('secret', $encrypter->decrypt($envelope));
    }

    #[RequiresPhpExtension('sodium')]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testRewrappingBindsTheStoredKeysUnderTheFirstSecret()
    {
        if (!class_exists(DriverManager::class) || !class_exists(DataKeyStore::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('rotated_store_secret', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();
        $container->get('test.store')->createTable();
        $kms = $container->get('test.kms');
        $connection = $container->get('test.dbal');
        $reference = new DataKeyStore($connection, new ServiceLocator(['default' => static fn (): object => $kms]), 'default', 'app', 'old')->current('user.email')->reference;

        $tester = new CommandTester($container->get('test.rewrap'));
        $tester->execute(['--to' => 'default', '--key-id' => 'app']);
        $tester->assertCommandIsSuccessful();

        $this->assertInstanceOf(DataKeyHandle::class, new DataKeyStore($connection, new ServiceLocator(['default' => static fn (): object => $kms]), 'default', 'app', 'new')->get($reference));
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheConfiguredDoctrineDbalTypesAreRegisteredWhenTheKernelBoots()
    {
        if (!class_exists(DriverManager::class) || !class_exists(EncryptedTypes::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('doctrine_dbal_types', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();
        $connection = $container->get('test.dbal');
        $types = method_exists($connection->getConfiguration(), 'getTypeProvider') ? $connection->getConfiguration()->getTypeProvider() : Type::getTypeRegistry();

        $this->assertInstanceOf(EncryptedType::class, $types->get('test_encrypted_phone'));

        $container->get('test.store')->createTable();
        $connection->executeStatement('CREATE TABLE speaker (id INTEGER PRIMARY KEY, phone BLOB)');
        $connection->insert('speaker', ['id' => 1, 'phone' => '+33 6 12 34 56 78'], ['phone' => 'test_encrypted_phone']);

        $stored = $connection->fetchOne('SELECT phone FROM speaker WHERE id = 1');
        $this->assertNotNull(Envelope::fromBytes($stored)->reference, 'the type encrypts with the store, as the envelope interfaces do.');
        $this->assertSame('+33 6 12 34 56 78', $connection->convertToPHPValue($stored, 'test_encrypted_phone'));
    }

    #[RequiresPhpExtension('sodium')]
    #[TestWith([false])]
    #[TestWith([true])]
    public function testTheDoctrineDbalTypesBuildTheStoreOnFirstUse(bool $debug)
    {
        if (!class_exists(DriverManager::class) || !class_exists(EncryptedTypes::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('doctrine_dbal_types', $debug, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertFalse($container->initialized('test.dbal'));

        $connection = $container->get('test.dbal');
        $container->get('test.store')->createTable();
        $stored = $connection->convertToDatabaseValue('+33 6 12 34 56 78', 'test_encrypted_phone');

        $this->assertSame('+33 6 12 34 56 78', $connection->convertToPHPValue($stored, 'test_encrypted_phone'));
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheDoctrineDbalTypesCanWrapATypeRegisteredWhenDoctrineCreatesTheConnection()
    {
        if (!class_exists(DriverManager::class) || !class_exists(EncryptedTypes::class)) {
            $this->markTestSkipped('doctrine/dbal or symfony/doctrine-dbal-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('doctrine_connections', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $connection = $container->get('doctrine.dbal.default_connection');
        $container->get('test.store')->createTable();
        $stored = $connection->convertToDatabaseValue('42.00', 'test_encrypted_amount');

        $this->assertNotNull(Envelope::fromBytes($stored)->reference, 'the type encrypts with the store, as the envelope interfaces do.');
        $this->assertSame('42.00', $connection->convertToPHPValue($stored, 'test_encrypted_amount'));
    }

    #[RequiresPhpExtension('sodium')]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testAnEncryptedPropertyOfAScannedEntityIsEncryptedAtRest()
    {
        if (!class_exists(EntityManager::class) || !class_exists(EncryptedColumnListener::class)) {
            $this->markTestSkipped('doctrine/orm or symfony/doctrine-orm-key-management is not installed.');
        }

        $kernel = new TestKeyManagementKernel('encrypted_columns', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();
        $connection = $container->get('test.dbal');
        $container->get('test.store')->createTable();

        $config = ORMSetup::createConfiguration(true);
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixtures/Entity'], true));
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $config->enableNativeLazyObjects(true);
        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, $container->get('test.encrypted_column_listener'));
        $entityManager = new EntityManager($connection, $config, $eventManager);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Speaker::class)]);

        $speaker = new Speaker();
        $speaker->phone = '+33 6 12 34 56 78';
        $entityManager->persist($speaker);
        $entityManager->flush();
        $entityManager->clear();

        $stored = $connection->fetchOne('SELECT phone FROM Speaker');
        $this->assertNotNull(Envelope::fromBytes($stored)->reference, 'the column encrypts with the store, as the envelope interfaces do.');
        $this->assertSame('+33 6 12 34 56 78', $entityManager->find(Speaker::class, $speaker->id)->phone);
    }

    #[RequiresPhpExtension('sodium')]
    public function testACompositeClientIsADefaultLikeAnyOtherAndEachMemberIsTraced()
    {
        $kernel = new TestKeyManagementKernel('redundant', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $kms = $container->get('test.kms');
        $this->assertInstanceOf(TraceableKms::class, $kms);
        $this->assertSame($container->get('test.redundant_kms'), $kms);
        $this->assertSame('secret', $kms->decrypt($kms->encrypt('app', 'secret')));

        $envelopeEncrypter = $container->get('test.envelope_encrypter');
        $this->assertSame('secret', $envelopeEncrypter->decrypt($envelopeEncrypter->encrypt('app', 'secret')));

        $collector = $container->get('test.data_collector');
        $collector->lateCollect();
        $services = $collector->getServices()[KeyManagementDataCollector::LAYER_KMS];

        $this->assertSame(['encrypt' => 1, 'decrypt' => 1, 'generate_data_key' => 1, 'unwrap_data_key' => 1], $services['redundant']['operations']);
        $this->assertSame(['encrypt' => 1, 'decrypt' => 1, 'generate_data_key' => 1, 'unwrap_data_key' => 1], $services['primary']['operations'], 'the first member is asked to read and answers.');
        $this->assertSame(['encrypt' => 2], $services['secondary']['operations'], 'the second member wraps everything and is never asked to read while the first one answers.');
    }

    public function testTheDataCollectorGoesWithTheProfiler()
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => true]));
        new KeyManagementBundle()->getContainerExtension()->load([['clients' => ['app' => 'sodium://?keys[app]=AAAA']]], $container);
        $this->assertTrue($container->hasDefinition('key_management.data_collector'));
        $this->assertSame([['service' => 'profiler']], $container->getDefinition('key_management.data_collector')->getTag('container.remove_if_missing'));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => true]));
        new KeyManagementBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertSame(['service_container'], array_keys($container->getDefinitions()));
        $this->assertSame([], $container->getAutoconfiguredInstanceof());
    }

    public function testTheProfilerPassIsRegistered()
    {
        $this->assertContains(KeyManagementPass::class, $this->buildPasses());
    }

    public function testTheFlysystemStoragesPassIsRegisteredWhenTheBridgeIsInstalled()
    {
        if (!class_exists(RegisterFlysystemStoragesPass::class)) {
            $this->markTestSkipped('symfony/flysystem-key-management is not installed.');
        }

        $this->assertContains(RegisterFlysystemStoragesPass::class, $this->buildPasses());
    }

    public function testTheEncryptedColumnsPassIsRegisteredWhenTheBridgeIsInstalled()
    {
        if (!class_exists(RegisterEncryptedColumnsPass::class)) {
            $this->markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $this->assertContains(RegisterEncryptedColumnsPass::class, $this->buildPasses());
    }

    public function testTheBlindIndexesPassIsRegisteredWhenTheBridgeIsInstalled()
    {
        if (!class_exists(RegisterBlindIndexesPass::class)) {
            $this->markTestSkipped('symfony/doctrine-orm-key-management is not installed.');
        }

        $this->assertContains(RegisterBlindIndexesPass::class, $this->buildPasses());
    }

    /**
     * @return list<string>
     */
    private function buildPasses(): array
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        new KeyManagementBundle()->build($container);

        return array_map(get_class(...), $container->getCompiler()->getPassConfig()->getPasses());
    }
}

class TestKeyManagementKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new KeyManagementBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $config = ['clients' => 'sodium://?keys[app]='.Base64UrlSafe::encode(random_bytes(32))];
        $container->parameters()->set('kernel.secret', 'kernel-secret');

        $services = $container->services();
        $services
            ->alias('test.kms', 'key_management.default')->public()
            ->alias('test.envelope_encrypter', 'key_management.envelope_encrypter.default')->public()
        ;

        if ('redundant' === $this->environment) {
            $config = [
                'clients' => [
                    'primary' => 'sodium://?keys[app]='.Base64UrlSafe::encode(random_bytes(32)),
                    'secondary' => 'sodium://?keys[backup]='.Base64UrlSafe::encode(random_bytes(32)),
                    'redundant' => ['members' => ['primary' => null, 'secondary' => 'backup']],
                ],
                'default_client' => 'redundant',
            ];
            $services
                ->alias('test.kms', EncrypterInterface::class)->public()
                ->alias('test.redundant_kms', 'key_management.redundant')->public()
                ->alias('test.envelope_encrypter', EnvelopeEncrypterInterface::class)->public()
            ;
        }

        if (\in_array($this->environment, ['env_dsn', 'env_service'], true)) {
            $config = ['clients' => ['default' => '%env(KMS_TEST_DSN)%']];
        }

        if (\in_array($this->environment, ['service', 'env_service'], true)) {
            $services->set('app.kms', SodiumKms::class)
                ->args([inline_service(InMemoryKeyLoader::class)->args([['app' => str_replace('%', '%%', random_bytes(32))]])])
                ->public();
        }

        if ('service' === $this->environment) {
            $config = ['clients' => 'service://app.kms'];
        }

        if ($this->isDebug()) {
            // the data collector is kept only when a profiler collects it
            $services->set('profiler', \stdClass::class);
            $services->alias('test.data_collector', 'key_management.data_collector')->public();
        }

        if ('store' === $this->environment) {
            $services->set('test.dbal', Connection::class)
                ->factory([DriverManager::class, 'getConnection'])
                ->args([['driver' => 'pdo_sqlite', 'memory' => true]]);
            $services->alias('test.stored_envelope_encrypter', 'key_management.stored_envelope_encrypter')->public();
            $config['store'] = ['connection' => 'test.dbal', 'client' => 'default', 'key_id' => 'app', 'read_self_contained' => true];
        }

        if (\in_array($this->environment, ['store_clients', 'trusted_store_clients', 'rotated_store_secret'], true)) {
            $config['clients'] = [
                'default' => $config['clients'],
                'public' => 'sodium-sealed-box://?keys[app]='.Base64UrlSafe::encode(sodium_crypto_box_seed_keypair(str_repeat('p', 32))),
                'legacy' => 'sodium://?keys[app]='.Base64UrlSafe::encode(random_bytes(32)),
            ];
            $config['store'] = ['connection' => 'test.dbal', 'client' => 'default', 'key_id' => 'app'];
            if ('trusted_store_clients' === $this->environment) {
                $config['store']['allowed_clients'] = ['legacy'];
            }
            if ('rotated_store_secret' === $this->environment) {
                $config['store']['secret'] = ['new', 'old'];
            }
            $services->set('test.dbal', Connection::class)
                ->factory([DriverManager::class, 'getConnection'])
                ->args([['driver' => 'pdo_sqlite', 'memory' => true]])
                ->public();
            $services->alias('test.store', 'key_management.store')->public();
            $services->alias('test.stored_envelope_encrypter', 'key_management.stored_envelope_encrypter')->public();
            $services->alias('test.rewrap', 'console.command.key_management_rewrap_data_keys')->public();
        }

        if ('doctrine_dbal_types' === $this->environment) {
            $services->set('test.dbal', Connection::class)
                ->factory([DriverManager::class, 'getConnection'])
                ->args([['driver' => 'pdo_sqlite', 'memory' => true]])
                ->public();
            $services->alias('test.store', 'key_management.store')->public();
            $config['store'] = ['connection' => 'test.dbal', 'key_id' => 'app'];
            $config['doctrine_dbal'] = ['types' => ['test_encrypted_phone' => ['type' => 'string', 'key' => 'speaker.phone']]];
        }

        if ('doctrine_connections' === $this->environment) {
            $services->set('doctrine.dbal.default_connection', Connection::class)
                ->factory([TestConnectionFactory::class, 'createConnection'])
                ->public();
            $services->alias('test.store', 'key_management.store')->public();
            $container->parameters()->set('doctrine.connections', ['default' => 'doctrine.dbal.default_connection']);
            $config['store'] = ['connection' => 'doctrine.dbal.default_connection', 'key_id' => 'app'];
            $config['doctrine_dbal'] = ['types' => ['test_encrypted_amount' => ['type' => 'test_amount', 'key' => 'invoice.amount']]];
        }

        if ('encrypted_columns' === $this->environment) {
            $services->set('test.dbal', Connection::class)
                ->factory([DriverManager::class, 'getConnection'])
                ->args([['driver' => 'pdo_sqlite', 'memory' => true]])
                ->public();
            $services->alias('test.store', 'key_management.store')->public();
            $services->alias('test.encrypted_column_listener', 'key_management.encrypted_column_listener')->public();
            $services->defaults()->autoconfigure();
            $services->load('Symfony\\Component\\KeyManagement\\Tests\\Fixtures\\Entity\\', 'Fixtures/Entity/*');
            $config['store'] = ['connection' => 'test.dbal', 'key_id' => 'app'];
        }

        $container->extension('key_management', $config);
    }
}

class TestConnectionFactory
{
    public static function createConnection(): Connection
    {
        // DoctrineBundle registers the types of its configuration when it creates the first connection, not before
        $types = method_exists(Configuration::class, 'getTypeProvider') ? (new Configuration())->getTypeProvider() : Type::getTypeRegistry();
        $types->has('test_amount') ? $types->override('test_amount', new StringType()) : $types->register('test_amount', new StringType());

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
