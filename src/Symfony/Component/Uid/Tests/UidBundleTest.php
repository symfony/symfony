<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver;
use Symfony\Component\Uid\Factory\NameBasedUuidFactory;
use Symfony\Component\Uid\Factory\TimeBasedUuidFactory;
use Symfony\Component\Uid\Factory\UlidFactory;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\UidBundle;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid47Transformer;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;

class UidBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_uid_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheFactoriesAreRegistered()
    {
        $container = $this->boot(['default_uuid_version' => 6]);

        $this->assertInstanceOf(UlidFactory::class, $container->get('test.ulid.factory'));
        $this->assertInstanceOf(Ulid::class, $container->get('test.ulid.factory')->create());

        $this->assertInstanceOf(UuidFactory::class, $container->get('test.uuid.factory'));
        $this->assertInstanceOf(UuidV6::class, $container->get('test.uuid.factory')->create());

        $this->assertInstanceOf(TimeBasedUuidFactory::class, $container->get('test.time_based_uuid.factory'));
        $this->assertInstanceOf(UuidV7::class, $container->get('test.time_based_uuid.factory')->create());

        $this->assertInstanceOf(NameBasedUuidFactory::class, $container->get('test.name_based_uuid.factory'));
        $this->assertInstanceOf(UuidV5::class, $container->get('test.name_based_uuid.factory')->create('symfony.com'));
    }

    public function testTheControllerArgumentResolverIsRegistered()
    {
        $this->assertInstanceOf(UidValueResolver::class, $this->boot()->get('test.argument_resolver.uid'));
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheUuid47TransformerUsesTheConfiguredSecret()
    {
        $transformer = $this->boot(['uuid47_secret' => 'a-high-entropy-secret'])->get('test.uuid47_transformer');

        $this->assertInstanceOf(Uuid47Transformer::class, $transformer);
        $this->assertEquals($uuid = new UuidV7(), $transformer->decode($transformer->encode($uuid)));
    }

    #[RequiresPhpExtension('sodium')]
    public function testTheUuid47TransformerFallsBackToTheKernelSecret()
    {
        $this->assertInstanceOf(Uuid47Transformer::class, $this->boot(secret: 'a-high-entropy-secret')->get('test.uuid47_transformer'));
    }

    public function testTheKernelBootsWithoutAKernelSecret()
    {
        $this->assertInstanceOf(UuidFactory::class, $this->boot()->get('test.uuid.factory'));
    }

    public function testTheUuid47TransformerIsRemovedWithoutAnySecret()
    {
        $container = new ContainerBuilder();
        $bundle = new UidBundle();
        $bundle->build($container);
        $bundle->getContainerExtension()->load([[]], $container);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();

        $this->assertTrue($container->hasDefinition('uuid.factory'));
        $this->assertFalse($container->hasDefinition('uuid47_transformer'));
        $this->assertFalse($container->hasAlias(Uuid47Transformer::class));
    }

    public function testNoServiceIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new UidBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('uuid.factory'));
    }

    private function boot(array $config = [], ?string $secret = null): object
    {
        $kernel = new TestUidKernel('test', true, $this->varDir.'/'.md5(serialize([$config, $secret])), $config, $secret);
        $kernel->boot();

        return $kernel->getContainer();
    }
}

class TestUidKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private array $config, private ?string $secret)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new UidBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        if (null !== $this->secret) {
            $container->parameters()->set('kernel.secret', $this->secret);
        }

        $container->extension('uid', ['name_based_uuid_namespace' => 'dns'] + $this->config);

        $services = $container->services()
            ->alias('test.ulid.factory', 'ulid.factory')->public()
            ->alias('test.uuid.factory', 'uuid.factory')->public()
            ->alias('test.name_based_uuid.factory', 'name_based_uuid.factory')->public()
            ->alias('test.time_based_uuid.factory', 'time_based_uuid.factory')->public()
            ->alias('test.argument_resolver.uid', 'argument_resolver.uid')->public()
        ;

        if (null !== $this->secret || isset($this->config['uuid47_secret'])) {
            $services->alias('test.uuid47_transformer', 'uuid47_transformer')->public();
        }
    }
}
