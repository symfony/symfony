<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\Cache\DependencyInjection\RemoveDefaultMarshallerSigningKeyPass;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;

class CacheBundleExtensionTest extends TestCase
{
    public function testTheDefaultPoolsAreRegistered()
    {
        $container = $this->createContainerFromFile('cache');

        $this->assertSame(FilesystemAdapter::class, $container->getDefinition('cache.app')->getClass());
        $this->assertTrue($container->getDefinition('cache.app')->isPublic());
        $this->assertTrue($container->getDefinition('cache.system')->isPublic());
        $this->assertSame(TagAwareAdapter::class, $container->getDefinition('cache.app.taggable')->getClass());

        $this->assertTrue($container->hasDefinition('cache.default_marshaller'));
        $this->assertTrue($container->hasDefinition('cache.default_clearer'));
        $this->assertTrue($container->hasDefinition('cache.system_clearer'));
        $this->assertTrue($container->hasDefinition('cache.global_clearer'));
    }

    #[RequiresPhpExtension('phpser')]
    #[TestWith(['secret'])]
    #[TestWith(['01234567890123456789012345678901'])]
    public function testTheDefaultMarshallerSignsWithTheKernelSecret(string $secret)
    {
        $container = $this->createContainerFromFile('cache', static function (ContainerBuilder $container) use ($secret) {
            $container->setParameter('kernel.secret', $secret);
        });
        $marshaller = $container->get('test.default_marshaller');
        $values = $marshaller->marshall(['a' => ['foo' => 'bar']], $failed);

        $this->assertSame([], $failed);
        $this->assertSame(phpser_serialize_signed(['foo' => 'bar'], hash_hkdf('sha256', $secret, 32, 'symfony.cache')), $values['a']);
        $this->assertSame(['foo' => 'bar'], $marshaller->unmarshall($values['a']));

        $this->expectException(\DomainException::class);
        $marshaller->unmarshall(phpser_serialize(['foo' => 'bar']));
    }

    #[RequiresPhpExtension('phpser')]
    public function testTheDefaultMarshallerResolvesTheSecretFromAnEnvVar()
    {
        $previousSecret = $_ENV['CACHE_SIGNING_SECRET'] ?? null;
        $_ENV['CACHE_SIGNING_SECRET'] = 'cache-signing-secret';

        try {
            $container = $this->createContainerFromFile('cache', static function (ContainerBuilder $container) {
                $container->setParameter('kernel.secret', '%env(CACHE_SIGNING_SECRET)%');
            }, true);
            $marshaller = $container->get('test.default_marshaller');
            $values = $marshaller->marshall(['a' => 'value'], $failed);

            $this->assertSame(phpser_serialize_signed('value', hash_hkdf('sha256', 'cache-signing-secret', 32, 'symfony.cache')), $values['a']);
        } finally {
            if (null === $previousSecret) {
                unset($_ENV['CACHE_SIGNING_SECRET']);
            } else {
                $_ENV['CACHE_SIGNING_SECRET'] = $previousSecret;
            }
        }
    }

    #[TestWith([null])]
    #[TestWith([''])]
    public function testTheDefaultMarshallerDoesNotNeedASecret(?string $secret)
    {
        $marshaller = $this->createContainerFromFile('cache', static function (ContainerBuilder $container) use ($secret) {
            if (null !== $secret) {
                $container->setParameter('kernel.secret', $secret);
            }
        })->get('test.default_marshaller');

        $this->assertSame(new DefaultMarshaller()->marshall(['a' => 'value'], $failed), $marshaller->marshall(['a' => 'value'], $failed));
    }

    /**
     * The pools of the sections that use them are declared by the bundles owning those sections.
     */
    #[DataProvider('provideSectionPools')]
    public function testTheSectionPoolsAreNotRegistered(string $id)
    {
        $this->assertFalse($this->createContainerFromFile('cache')->has($id));
    }

    public static function provideSectionPools(): iterable
    {
        yield ['cache.validator'];
        yield ['cache.serializer'];
        yield ['cache.property_info'];
        yield ['cache.property_access'];
        yield ['cache.asset_mapper'];
        yield ['cache.messenger.restart_workers_signal'];
        yield ['cache.scheduler'];
    }

    public function testTheConfiguredPoolsAreRegistered()
    {
        $container = $this->createContainerFromFile('cache');

        $foo = $container->getDefinition('cache.foo');
        $this->assertSame(30, $foo->getTag('cache.pool')[0]['default_lifetime']);

        $bar = $container->getDefinition('cache.bar');
        $this->assertSame(TagAwareAdapter::class, $bar->getClass());
        $this->assertSame('.cache.bar.inner', (string) $bar->getArgument(0));
    }

    #[TestWith(['cache.adapter.mongodb'])]
    #[TestWith(['cache.adapter.mongodb_tag_aware'])]
    public function testTheMongoDbAdaptersUseTheClock(string $id)
    {
        $adapter = $this->createContainerFromFile('cache')->getDefinition($id);

        $this->assertEquals(new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE), $adapter->getArguments()[5] ?? null);
    }

    public function testTheDefaultProviderDeducesTheAdapterFromTheDsn()
    {
        $container = $this->createContainerFromFile('cache_default_provider');

        $app = $container->getDefinition('cache.app');
        $this->assertSame([AbstractAdapter::class, 'createAdapter'], $app->getFactory());

        $connection = $container->getDefinition((string) $app->getArgument(0));
        $this->assertSame([AbstractAdapter::class, 'createConnection'], $connection->getFactory());
        $this->assertStringContainsString('APP_CACHE_DSN', $connection->getArgument(0));

        $this->assertSame('my-app', $container->getParameter('cache.prefix.seed'));
    }

    public function testAPoolCanBeAChildOfAPoolDeclaredByAnotherExtension()
    {
        $container = $this->createContainerFromFile('cache', static function (ContainerBuilder $container) {
            $container->setDefinition('cache.acme', new ChildDefinition('cache.system'))->addTag('cache.pool');
        });

        $this->assertSame($container->getDefinition('cache.system')->getClass(), $container->getDefinition('cache.acme')->getClass());
    }

    private function createContainerFromFile(string $file, ?\Closure $configure = null, bool $resolveEnvPlaceholders = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => false,
            'kernel.cache_dir' => __DIR__,
            'kernel.share_dir' => __DIR__,
            'kernel.project_dir' => __DIR__,
            'kernel.container_class' => 'testContainer',
            'container.build_id' => 'abc123',
        ]));
        $container->setResourceTracking(false);
        $container->registerExtension(new CacheBundle()->getContainerExtension());
        $container->setAlias('test.default_marshaller', 'cache.default_marshaller')->setPublic(true);
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');
        $configure?->__invoke($container);
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([new RemoveDefaultMarshallerSigningKeyPass()]);
        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile($resolveEnvPlaceholders);

        return $container;
    }
}
