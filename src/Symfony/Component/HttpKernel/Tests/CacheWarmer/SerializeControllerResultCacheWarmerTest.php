<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheWarmer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\HttpKernel\CacheWarmer\SerializeControllerResultCacheWarmer;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\EventListener\SerializeControllerResultAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class SerializeControllerResultCacheWarmerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sf_serialize_controller_result_cache_warmer_test';
        self::removeDir($this->dir);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        self::removeDir($this->dir);
    }

    public function testWarmUpResolvesTheReturnTypeOfTheControllersWhoseResultIsStreamedWithoutMapping()
    {
        $warmer = new SerializeControllerResultCacheWarmer([StreamingController::class, ClassLevelStreamingController::class], $this->dir.'/types.php', TypeResolver::create());
        $warmer->warmUp($this->dir, $this->dir);

        $pool = new PhpArrayAdapter($this->dir.'/types.php', new NullAdapter());

        $this->assertEquals(Type::list(Type::object(StreamedProduct::class)), $pool->getItem(rawurlencode(StreamingController::class.'::list'))->get());
        $this->assertEquals(Type::object(StreamedProduct::class), $pool->getItem(rawurlencode(ClassLevelStreamingController::class.'::__invoke'))->get());

        foreach (['mapped', 'serialized', 'untyped', 'notAnnotated'] as $method) {
            $this->assertFalse($pool->getItem(rawurlencode(StreamingController::class.'::'.$method))->isHit(), $method);
        }
        $this->assertFalse($pool->getItem(rawurlencode(ClassLevelStreamingController::class.'::serialized'))->isHit());
    }

    #[DataProvider('provideControllers')]
    public function testTheListenerUsesTheWarmedUpType(callable $controller, mixed $controllerResult, string $expectedContent)
    {
        $warmer = new SerializeControllerResultCacheWarmer([StreamingController::class, ClassLevelStreamingController::class], $this->dir.'/types.php', TypeResolver::create());
        $warmer->warmUp($this->dir, $this->dir);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $viewEvent = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $controllerResult, new ControllerArgumentsMetadata(
            new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST),
            new ControllerArgumentsEvent($kernel, $controller, [], $request, HttpKernelInterface::MAIN_REQUEST),
        ));

        $typeCache = PhpArrayAdapter::create($this->dir.'/types.php', new NullAdapter());
        $listener = new SerializeControllerResultAttributeListener(null, null, JsonStreamWriter::create([], $this->dir.'/stream_writer'), fn () => $this->fail('The type resolver should not be resolved.'), $typeCache);
        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));

        $content = '';
        ob_start(static function (string $chunk) use (&$content) {
            $content .= $chunk;

            return '';
        });

        try {
            $viewEvent->getResponse()->sendContent();
        } finally {
            ob_end_clean();
        }

        $this->assertSame($expectedContent, $content);
    }

    public static function provideControllers(): iterable
    {
        yield 'method' => [[new StreamingController(), 'list'], [new StreamedProduct(1)], '[{"id":1}]'];
        yield 'invokable' => [new ClassLevelStreamingController(), new StreamedProduct(2), '{"id":2}'];
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }

        rmdir($dir);
    }
}

class StreamedProduct
{
    public function __construct(public readonly int $id)
    {
    }
}

class StreamingController
{
    /**
     * @return list<StreamedProduct>
     */
    #[Serialize(stream: true)]
    public function list(): array
    {
        return [];
    }

    #[Serialize(map: StreamedProduct::class, stream: true)]
    public function mapped(): array
    {
        return [];
    }

    #[Serialize]
    public function serialized(): StreamedProduct
    {
        return new StreamedProduct(1);
    }

    #[Serialize(stream: true)]
    public function untyped()
    {
        return new StreamedProduct(1);
    }

    public function notAnnotated(): StreamedProduct
    {
        return new StreamedProduct(1);
    }
}

#[Serialize(stream: true)]
class ClassLevelStreamingController
{
    public function __invoke(): StreamedProduct
    {
        return new StreamedProduct(1);
    }

    #[Serialize]
    public function serialized(): StreamedProduct
    {
        return new StreamedProduct(1);
    }
}
