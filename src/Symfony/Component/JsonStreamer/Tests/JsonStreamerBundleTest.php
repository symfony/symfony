<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonStreamer\JsonStreamerBundle;
use Symfony\Component\JsonStreamer\StreamReaderInterface;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\StreamableDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\HeightValueObjectTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueObject\Height;
use Symfony\Component\TypeInfo\Type;

class JsonStreamerBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_json_streamer_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheStreamWriterUsesTheAutoconfiguredTransformers()
    {
        $writer = $this->boot()->get('test.stream_writer');

        $dummy = new StreamableDummy();
        $dummy->height = new Height(10, 'meters');

        $this->assertInstanceOf(StreamWriterInterface::class, $writer);
        $this->assertSame('{"@id":1,"height":"10 meters"}', (string) $writer->write($dummy, Type::object(StreamableDummy::class)));
    }

    public function testTheStreamReaderUsesTheAutoconfiguredTransformers()
    {
        $reader = $this->boot()->get('test.stream_reader');

        $expected = new StreamableDummy();
        $expected->id = 2;
        $expected->height = new Height(20, 'meters');

        $this->assertInstanceOf(StreamReaderInterface::class, $reader);
        $this->assertEquals($expected, $reader->read('{"@id": 2, "height": "20 meters"}', Type::object(StreamableDummy::class)));
    }

    public function testTheDefaultOptionsAreForwarded()
    {
        $writer = $this->boot(['default_options' => ['include_null_properties' => true]])->get('test.stream_writer');

        $dummy = new StreamableDummy();
        $dummy->height = new Height(10, 'meters');

        $this->assertSame('{"@id":1,"name":null,"height":"10 meters"}', (string) $writer->write($dummy, Type::object(StreamableDummy::class)));
    }

    public function testTheStreamableClassesAreWarmedUp()
    {
        $container = $this->boot();
        $streamWritersDir = $container->getParameter('kernel.cache_dir').'/json_streamer/stream_writer';

        $container->get('test.cache_warmer')->warmUp($container->getParameter('kernel.cache_dir'));

        $this->assertCount(2, glob($streamWritersDir.'/*.php'));
    }

    public function testTypeInfoIsReportedAsRequired()
    {
        $kernel = new TestJsonStreamerKernel('no_type_info', true, $this->varDir.'/no_type_info', withTypeInfo: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JsonStreamer support cannot be enabled as the TypeInfo component is not enabled. Try setting "type_info.enabled" to true.');

        $kernel->boot();
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new JsonStreamerBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('json_streamer.stream_writer'));
        $this->assertFalse($container->hasDefinition('.json_streamer.cache_warmer.streamer'));
    }

    private function boot(array $config = []): object
    {
        $kernel = new TestJsonStreamerKernel('test', true, $this->varDir.'/'.md5(serialize($config)), $config);
        $kernel->boot();

        return $kernel->getContainer();
    }
}

class TestJsonStreamerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private array $config = [], private bool $withTypeInfo = true)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new JsonStreamerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('json_streamer', $this->config);

        if (!$this->withTypeInfo) {
            $container->extension('type_info', ['enabled' => false]);
        }

        $container->services()
            ->set(StreamableDummy::class)->autoconfigure()
            ->set(HeightValueObjectTransformer::class)->autoconfigure()
            ->alias('test.stream_writer', 'json_streamer.stream_writer')->public()
            ->alias('test.stream_reader', 'json_streamer.stream_reader')->public()
            ->alias('test.cache_warmer', '.json_streamer.cache_warmer.streamer')->public()
        ;
    }
}
