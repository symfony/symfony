<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonPath\Attribute\AsJsonPathFunction;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\FunctionReturnType;
use Symfony\Component\JsonPath\JsonPathBundle;
use Symfony\Component\JsonPath\JsonPathCrawler;
use Symfony\Component\JsonPath\JsonPathCrawlerInterface;

class JsonPathBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_json_path_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testCrawlerIsWiredToTaggedFunctions()
    {
        $container = new ContainerBuilder();
        $bundle = new JsonPathBundle();
        $bundle->build($container);
        $bundle->getContainerExtension()->load([[]], $container);

        $this->assertTrue($container->hasDefinition('json_path.crawler'));
        $this->assertSame('json_path.crawler', (string) $container->getAlias(JsonPathCrawlerInterface::class));

        $locatorArgument = $container->getDefinition('json_path.crawler')->getArgument(0);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locatorArgument);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $locatorArgument->getTaggedIteratorArgument());
        $this->assertSame('json_path.function', $locatorArgument->getTaggedIteratorArgument()->getTag());
        $this->assertSame('name', $locatorArgument->getTaggedIteratorArgument()->getIndexAttribute());
    }

    public function testCrawlerIsRegistered()
    {
        $kernel = new JsonPathBundleTestKernel('test', true, $this->varDir);
        $kernel->boot();

        $this->assertInstanceOf(JsonPathCrawler::class, $kernel->getContainer()->get('test.json_path.crawler'));
    }

    public function testCrawlerInterfaceIsAliased()
    {
        $kernel = new JsonPathBundleTestKernel('test', true, $this->varDir);
        $kernel->boot();

        $this->assertInstanceOf(JsonPathCrawlerInterface::class, $kernel->getContainer()->get('test.json_path.crawler_interface'));
    }

    public function testFunctionAttributeIsAutoconfigured()
    {
        $kernel = new JsonPathBundleTestKernel('test', true, $this->varDir);
        $kernel->boot();

        $crawler = $kernel->getContainer()->get('test.json_path.crawler');
        $result = $crawler->crawl('{"items": [{"title": "hello"}, {"title": "world"}]}')->find('$.items[?upper(@.title) == "HELLO"]');

        $this->assertSame([['title' => 'hello']], $result);
    }

    public function testFunctionArityIsCollected()
    {
        $kernel = new JsonPathBundleTestKernel('test', true, $this->varDir);
        $kernel->boot();

        $crawler = $kernel->getContainer()->get('test.json_path.crawler');

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('the JsonPath function "upper" requires exactly 1 argument(s)');

        $crawler->crawl('{"items": [{"title": "hello"}]}')->find('$.items[?upper(@.title, @.title) == "HELLO"]');
    }

    public function testFunctionReturnTypeIsCollected()
    {
        $kernel = new JsonPathBundleTestKernel('test', true, $this->varDir);
        $kernel->boot();

        $crawler = $kernel->getContainer()->get('test.json_path.crawler');

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('the result of the custom JsonPath function "has_title" (LogicalType) cannot be used in comparisons');

        $crawler->crawl('{"items": [{"title": "hello"}]}')->find('$.items[?has_title(@.title) == true]');
    }

    public function testNotInvokableFunctionIsRejected()
    {
        $kernel = new InvalidJsonPathBundleTestKernel('test', true, $this->varDir);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" attribute can only be applied to invokable classes, "%s" is not invokable.', AsJsonPathFunction::class, JsonPathBundleTestNotInvokable::class));

        $kernel->boot();
    }
}

class JsonPathBundleTestKernel extends AbstractKernel
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
        yield new JsonPathBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register(JsonPathBundleTestUppercase::class, JsonPathBundleTestUppercase::class)
            ->setAutoconfigured(true);
        $container->register(JsonPathBundleTestHasTitle::class, JsonPathBundleTestHasTitle::class)
            ->setAutoconfigured(true);
        $container->setAlias('test.json_path.crawler', 'json_path.crawler')
            ->setPublic(true);
        $container->setAlias('test.json_path.crawler_interface', JsonPathCrawlerInterface::class)
            ->setPublic(true);
    }
}

class InvalidJsonPathBundleTestKernel extends AbstractKernel
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
        yield new JsonPathBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register(JsonPathBundleTestNotInvokable::class, JsonPathBundleTestNotInvokable::class)
            ->setAutoconfigured(true);
    }
}

#[AsJsonPathFunction('upper')]
final class JsonPathBundleTestUppercase
{
    public function __invoke(mixed $value): ?string
    {
        return \is_string($value) ? strtoupper($value) : null;
    }
}

#[AsJsonPathFunction('has_title', FunctionReturnType::Logical)]
final class JsonPathBundleTestHasTitle
{
    public function __invoke(mixed $value): bool
    {
        return \is_string($value) && '' !== $value;
    }
}

#[AsJsonPathFunction('broken')]
final class JsonPathBundleTestNotInvokable
{
}
