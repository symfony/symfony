<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\AssetMapper\AssetMapperDevServerSubscriber;
use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\RequestStack;

class AssetMapperBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_asset_mapper_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheServicesAreWiredInAKernel()
    {
        $kernel = new TestAssetMapperKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertInstanceOf(MapperAwareAssetPackage::class, $container->get('test.asset_package'));
        $this->assertInstanceOf(AssetMapperDevServerSubscriber::class, $container->get('test.dev_server_subscriber'));
        $this->assertInstanceOf(ImportMapManager::class, $container->get('test.importmap_manager'));
        $this->assertInstanceOf(ArrayAdapter::class, $container->get('test.cache'));
    }

    public function testTheImportMapCannotBeUsedWithoutHttpClient()
    {
        $kernel = new TestAssetMapperKernel('test', true, $this->varDir, withHttpClient: false);
        $kernel->boot();

        $this->expectExceptionMessage('You cannot use the AssetMapper integration since the HttpClient component is not enabled.');

        $kernel->getContainer()->get('test.importmap_manager');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testTheDevServerFollowsTheDebugMode(bool $debug)
    {
        $container = $this->createContainer([], $debug);

        $this->assertSame($debug, $container->hasDefinition('asset_mapper.dev_server_subscriber'));
    }

    public function testTheDevServerCanBeTurnedOnInProduction()
    {
        $container = $this->createContainer(['server' => true], false);

        $this->assertTrue($container->hasDefinition('asset_mapper.dev_server_subscriber'));
    }

    public function testThePublicDirectoryIsReadFromComposer()
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->varDir);
        $filesystem->dumpFile($this->varDir.'/composer.json', '{"extra":{"public-dir":"web"}}');

        $container = $this->createContainer(['public_prefix' => '/static/']);

        $this->assertSame($this->varDir.'/web', $container->getDefinition('asset_mapper.local_public_assets_filesystem')->getArgument(0));
        $this->assertSame($this->varDir.'/web/static', $container->getDefinition('asset_mapper.compiled_asset_mapper_config_reader')->getArgument(0));
    }

    public function testTheBundlePublicDirectoriesAreMapped()
    {
        new Filesystem()->mkdir($this->varDir.'/public');

        $container = $this->createContainer(['paths' => ['assets/']], bundles: ['AcmeBundle' => ['path' => $this->varDir]]);

        $this->assertSame([
            'assets/' => '',
            $this->varDir.'/public' => 'bundles/acme',
        ], $container->getDefinition('asset_mapper.repository')->getArgument(0));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = $this->createContainer(['enabled' => false]);

        $this->assertFalse($container->hasDefinition('asset_mapper'));
        $this->assertFalse($container->hasDefinition('cache.asset_mapper'));
    }

    public function testDefaultConfig()
    {
        $config = new Processor()->processConfiguration($this->getConfiguration(), [[]]);

        $this->assertSame([
            'enabled' => true,
            'paths' => [],
            'excluded_patterns' => [],
            'exclude_dotfiles' => true,
            'server' => '%kernel.debug%',
            'public_prefix' => '/assets/',
            'missing_import_mode' => 'warn',
            'extensions' => [],
            'importmap_path' => '%kernel.project_dir%/importmap.php',
            'importmap_polyfill' => 'es-module-shims',
            'importmap_entries' => 'all',
            'importmap_script_attributes' => [],
            'importmap_integrity_algorithms' => [],
            'vendor_dir' => '%kernel.project_dir%/assets/vendor',
            'minimum_release_age' => 0,
            'precompress' => [
                'enabled' => false,
                'formats' => [],
                'extensions' => CompressorInterface::DEFAULT_EXTENSIONS,
            ],
        ], $config);
    }

    #[DataProvider('provideImportmapPolyfillTests')]
    public function testImportmapPolyfillValue(mixed $polyfillValue, bool $isValid, mixed $expected)
    {
        if (!$isValid) {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage($expected);
        }

        $config = new Processor()->processConfiguration($this->getConfiguration(), [null === $polyfillValue ? [] : [
            'importmap_polyfill' => $polyfillValue,
        ]]);

        if ($isValid) {
            $this->assertSame($expected, $config['importmap_polyfill']);
        }
    }

    public static function provideImportmapPolyfillTests(): iterable
    {
        yield [true, false, 'Must be either an importmap name or false.'];
        yield [null, true, 'es-module-shims'];
        yield ['es-module-shims', true, 'es-module-shims'];
        yield ['foo', true, 'foo'];
        yield [false, true, false];
    }

    #[TestWith([['sha384'], ['sha384']])]
    #[TestWith([['sha256', 'sha512'], ['sha256', 'sha512']])]
    public function testImportmapIntegrityAlgorithms(array $algorithms, array $expected)
    {
        $config = new Processor()->processConfiguration($this->getConfiguration(), [[
            'importmap_integrity_algorithms' => $algorithms,
        ]]);

        $this->assertSame($expected, $config['importmap_integrity_algorithms']);
    }

    private function getConfiguration(): Configuration
    {
        return new Configuration(new AssetMapperBundle(), null, 'asset_mapper');
    }

    private function createContainer(array $config = [], bool $debug = true, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => $debug,
            'kernel.project_dir' => $this->varDir,
            'kernel.bundles_metadata' => $bundles,
        ]));
        new AssetMapperBundle()->getContainerExtension()->load([$config], $container);

        return $container;
    }
}

class TestAssetMapperKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(
        string $env,
        bool $debug,
        private string $dir,
        private bool $withHttpClient = true,
    ) {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new AssetMapperBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('kernel.charset', 'UTF-8');

        $services = $container->services();
        $services
            ->set('logger', NullLogger::class)
            ->set('request_stack', RequestStack::class)
            ->set('cache.system', ArrayAdapter::class)
            ->set('assets.empty_version_strategy', EmptyVersionStrategy::class)
            ->set('assets._default_package', Package::class)
                ->args([new Reference('assets.empty_version_strategy')])
            ->alias('test.asset_package', 'assets._default_package')->public()
            ->alias('test.cache', 'cache.asset_mapper')->public()
            ->alias('test.dev_server_subscriber', 'asset_mapper.dev_server_subscriber')->public()
            ->alias('test.importmap_manager', 'asset_mapper.importmap.manager')->public()
        ;

        if ($this->withHttpClient) {
            $services->set('http_client', MockHttpClient::class);
        }
    }
}
