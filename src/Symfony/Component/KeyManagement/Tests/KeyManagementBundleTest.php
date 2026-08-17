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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterBlindIndexesPass;
use Symfony\Component\KeyManagement\Bridge\Flysystem\DependencyInjection\RegisterFlysystemStoragesPass;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\Debug\TraceableKms;
use Symfony\Component\KeyManagement\DependencyInjection\KeyManagementPass;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\KeyManagementBundle;

class KeyManagementBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_key_management_bundle_test';
    }

    protected function tearDown(): void
    {
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

    /**
     * A self-contained envelope read through the stored encrypter goes through the fallback, which
     * is the default client's envelope encrypter: what the profiler must report is one read.
     */
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

    /**
     * The pass is what makes the storages of league/flysystem-bundle answer to the host of a
     * "...+fly://" DSN; without it registered, every such DSN names a service the factory has no
     * way of seeing.
     */
    public function testTheFlysystemStoragesPassIsRegisteredWhenTheBridgeIsInstalled()
    {
        if (!class_exists(RegisterFlysystemStoragesPass::class)) {
            $this->markTestSkipped('symfony/flysystem-key-management is not installed.');
        }

        $this->assertContains(RegisterFlysystemStoragesPass::class, $this->buildPasses());
    }

    /**
     * The pass is what hands the listener the blind indexes of the application; without it
     * registered, the listener keeps the empty locator the extension gave it and every entity
     * carrying a "#[BlindIndexed]" property fails on a flush.
     */
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

        $services = $container->services();
        $services
            ->alias('test.kms', 'key_management.default')->public()
            ->alias('test.envelope_encrypter', 'key_management.envelope_encrypter.default')->public()
        ;

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
            $config['store'] = ['connection' => 'test.dbal', 'client' => 'default', 'key_id' => 'app'];
        }

        $container->extension('key_management', $config);
    }
}
