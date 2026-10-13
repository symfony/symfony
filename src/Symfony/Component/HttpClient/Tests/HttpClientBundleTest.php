<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClientBundle;
use Symfony\Component\HttpClient\RecorderHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_http_client_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheDefaultClientIsRegistered()
    {
        $kernel = new TestHttpClientKernel('default', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $client = $container->get('test.client');
        $this->assertInstanceOf(HttpClientInterface::class, $client);

        $this->assertSame('{"foo":"bar"}', $client->request('GET', 'https://example.com/')->getContent());
    }

    public function testScopedClientsAreRegistered()
    {
        $kernel = new TestHttpClientKernel('scoped', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $response = $container->get('test.scoped_client')->request('GET', '/path');
        $this->assertSame('https://example.com/path', $response->getInfo('url'));
        $this->assertContains('X-Scoped: yes', $response->getRequestOptions()['headers']);

        $this->assertSame($container->get('test.scoped_client'), $container->get('test.autowired_scoped_client')->client);
    }

    public function testTheClientsAreTracedInDebugMode()
    {
        $kernel = new TestHttpClientKernel('debug', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $collector = $container->get('test.data_collector');
        $container->get('test.client')->request('GET', 'https://example.com/')->getContent();
        $collector->lateCollect();

        $this->assertSame(1, $collector->getRequestCount());
        $this->assertSame(['http_client', 'scoped_client', 'cached_client'], array_keys($collector->getClients()));
    }

    public function testTheCachePoolAndTheDataCollectorGoWithWhatTheyNeed()
    {
        $container = $this->createContainer();
        new HttpClientBundle()->getContainerExtension()->load([[]], $container);

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);
        $this->assertFalse($container->hasDefinition('cache.http_client.pool'), 'dropped without "cache.app"');
        $this->assertFalse($container->hasDefinition('cache.http_client'), 'and the tag-aware adapter goes with the pool');

        $container = $this->createContainer();
        $container->register('cache.app');
        new HttpClientBundle()->getContainerExtension()->load([[]], $container);

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);
        $this->assertTrue($container->hasDefinition('cache.http_client.pool'));
        $this->assertTrue($container->hasDefinition('cache.http_client'));
    }

    private function createContainer(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new HttpClientBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('http_client'));
        $this->assertFalse($container->hasDefinition('http_client.transport'));
        $this->assertFalse($container->hasDefinition('cache.http_client'));
    }

    public function testTheRecorderIsWiredWhenEnabled()
    {
        $container = $this->loadExtension([
            'default_options' => ['headers' => ['X-Foo' => 'bar']],
            'recorder' => [
                'enabled' => true,
                'redact' => ['X-Custom-Secret', 'sig'],
                'redact_except' => ['code'],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('http_client.recorder'));
        $definition = $container->getDefinition('http_client.recorder');
        $this->assertSame(RecorderHttpClient::class, $definition->getClass());

        $this->assertSame(['http_client.transport', null, 100], $definition->getDecoratedService());

        $arguments = $definition->getArguments();
        $this->assertCount(4, $arguments);
        $this->assertSame('.inner', (string) $arguments[0]);
        $this->assertSame('http_client.recorder.configuration', (string) $arguments[1]);
        $this->assertSame('http_client.recorder.redactor', (string) $arguments[2]);

        $this->assertSame($container->getDefinition('http_client.transport')->getArgument(0), $arguments[3]);
        $this->assertSame('bar', $arguments[3]['headers']['X-Foo'] ?? null);

        $this->assertSame([['X-Custom-Secret', 'sig'], ['code']], $container->getDefinition('http_client.recorder.redactor')->getArguments());
    }

    public function testTheRecorderRedactorCanBeReplaced()
    {
        $container = $this->loadExtension(['recorder' => ['enabled' => true, 'redactor' => 'my_redactor']]);

        $this->assertSame('my_redactor', (string) $container->getAlias('http_client.recorder.redactor'));
    }

    public function testTheRecorderIsDisabledByDefault()
    {
        $this->assertFalse($this->loadExtension([])->hasDefinition('http_client.recorder'));
    }

    #[DataProvider('provideFrameworkConfigsEnablingTest')]
    public function testTheRecorderIsEnabledWhenFrameworkTestIsEnabled(array $frameworkConfigs)
    {
        $this->assertTrue($this->loadExtension([], $frameworkConfigs)->hasDefinition('http_client.recorder'));
        $this->assertTrue($this->loadExtension(['recorder' => ['redact' => ['pin']]], $frameworkConfigs)->hasDefinition('http_client.recorder'));
    }

    public static function provideFrameworkConfigsEnablingTest(): iterable
    {
        yield 'true' => [[['test' => true]]];
        yield 'null' => [[['test' => null]]];
        yield 'parameter' => [[['test' => '%app.test%']]];
        yield 'last one wins' => [[['test' => false], ['test' => true], ['secret' => 's3cr3t']]];
    }

    #[DataProvider('provideFrameworkConfigsNotEnablingTest')]
    public function testTheRecorderIsDisabledWhenFrameworkTestIsNotEnabled(array $frameworkConfigs)
    {
        $this->assertFalse($this->loadExtension([], $frameworkConfigs)->hasDefinition('http_client.recorder'));
        $this->assertFalse($this->loadExtension(['recorder' => ['redact' => ['pin']]], $frameworkConfigs)->hasDefinition('http_client.recorder'));
    }

    public static function provideFrameworkConfigsNotEnablingTest(): iterable
    {
        yield 'unset' => [[['secret' => 's3cr3t']]];
        yield 'false' => [[['test' => false]]];
        yield 'last one wins' => [[['test' => true], ['test' => false]]];
        yield 'undefined env var' => [[['test' => '%env(bool:UNDEFINED_FRAMEWORK_TEST)%']]];
    }

    #[TestWith(['true', true])]
    #[TestWith(['false', false])]
    public function testFrameworkTestSetByAnEnvVarDrivesTheRecorder(string $env, bool $enabled)
    {
        $_ENV['FRAMEWORK_TEST'] = $env;

        try {
            $container = $this->loadExtension([], [['test' => '%env(bool:FRAMEWORK_TEST)%']]);
        } finally {
            unset($_ENV['FRAMEWORK_TEST']);
        }

        $this->assertSame($enabled, $container->hasDefinition('http_client.recorder'));
    }

    #[DataProvider('provideExplicitRecorderConfigs')]
    public function testAnExplicitRecorderConfigWinsOverFrameworkTest(mixed $recorder, bool $enabled)
    {
        $this->assertSame($enabled, $this->loadExtension(['recorder' => $recorder], [['test' => true]])->hasDefinition('http_client.recorder'));
        $this->assertSame($enabled, $this->loadExtension(['recorder' => $recorder], [['test' => false]])->hasDefinition('http_client.recorder'));
    }

    public static function provideExplicitRecorderConfigs(): iterable
    {
        yield 'true' => [true, true];
        yield 'null' => [null, true];
        yield 'enabled' => [['enabled' => true], true];
        yield 'false' => [false, false];
        yield 'disabled' => [['enabled' => false, 'redact' => ['pin']], false];
    }

    private function loadExtension(array $config, array $frameworkConfigs = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('app.test', true);
        $container->registerExtension(new HttpClientBundle()->getContainerExtension());
        $container->loadFromExtension('http_client', $config);

        if ($frameworkConfigs) {
            $container->registerExtension(new class extends Extension {
                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return 'framework';
                }
            });

            foreach ($frameworkConfigs as $frameworkConfig) {
                $container->loadFromExtension('framework', $frameworkConfig);
            }
        }

        new MergeExtensionConfigurationPass()->process($container);

        return $container;
    }
}

class TestHttpClientKernel extends AbstractKernel
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
        yield new HttpClientBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('http_client', [
            'mock_response_factory' => 'test.mock_factory',
            'scoped_clients' => [
                'scoped_client' => [
                    'base_uri' => 'https://example.com/',
                    'headers' => ['X-Scoped' => 'yes'],
                ],
                'cached_client' => [
                    'base_uri' => 'https://example.com/',
                    'caching' => ['cache_pool' => 'test.cache_pool'],
                ],
            ],
        ]);

        $services = $container->services();
        $services
            ->set('test.mock_factory', MockResponseFactory::class)
            ->set('test.cache_pool', ArrayAdapter::class)
            ->set('logger', NullLogger::class)
            ->set('test.autowired_scoped_client', AutowiredScopedClient::class)
                ->autowire()
                ->public()
            ->alias('test.client', 'http_client')->public()
            ->alias('test.scoped_client', 'scoped_client')->public()
            ->alias('test.cached_client', 'cached_client')->public()
        ;

        if ($this->debug) {
            // the data collector is kept only when a profiler collects it
            $services->set('profiler', \stdClass::class);
            $services->alias('test.data_collector', 'data_collector.http_client')->public();
        }
    }
}

class AutowiredScopedClient
{
    public function __construct(
        #[Target('scoped_client')]
        public HttpClientInterface $client,
    ) {
    }
}

class MockResponseFactory
{
    public function __invoke(): MockResponse
    {
        return new MockResponse('{"foo":"bar"}');
    }
}
