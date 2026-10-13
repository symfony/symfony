<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\SerializeControllerResultAttributeListener;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class SerializeControllerResultListenerTest extends TestCase
{
    private string $streamWritersDir;

    protected function setUp(): void
    {
        $this->streamWritersDir = \sprintf('%s/symfony_http_kernel_serialize_test/stream_writer', sys_get_temp_dir());

        if (is_dir($this->streamWritersDir)) {
            array_map('unlink', glob($this->streamWritersDir.'/*'));
            rmdir($this->streamWritersDir);
        }
    }

    public function testSerializeAttribute()
    {
        $controllerResult = new ProductCreated(10);
        $responseBody = '{"productId": 10}';

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($controllerResult, 'json', ['foo' => 'bar'])
            ->willReturn($responseBody);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();

        $controller = new GetApiController();
        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $viewEvent = new ViewEvent(
            $kernel,
            $request,
            $requestType,
            $controllerResult,
            new ControllerArgumentsMetadata(
                new ControllerEvent($kernel, $controller, $request, $requestType),
                new ControllerArgumentsEvent(
                    $kernel,
                    $controller,
                    [],
                    $request,
                    $requestType,
                ),
            ),
        );

        $listener = new SerializeControllerResultAttributeListener($serializer);
        $listener->onView(new ControllerAttributeEvent(new Serialize(201, ['X-Test-Header' => 'abc'], ['foo' => 'bar']), $viewEvent));

        $response = $viewEvent->getResponse();

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($responseBody, $response->getContent());
        self::assertSame('abc', $response->headers->get('X-Test-Header'));
    }

    public function testSerializeAttributeOnClass()
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $controller = new ClassLevelSerializeController();
        $requestType = HttpKernelInterface::MAIN_REQUEST;

        $controllerEvent = new ControllerEvent($kernel, $controller, $request, $requestType);
        $attributes = $controllerEvent->getAttributes(Serialize::class);

        self::assertCount(1, $attributes);
        self::assertSame(201, $attributes[0]->code);

        $controllerResult = new ProductCreated(10);
        $responseBody = '{"productId": 10}';

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($controllerResult, 'json', ['foo' => 'bar'])
            ->willReturn($responseBody);

        $viewEvent = new ViewEvent(
            $kernel,
            $request,
            $requestType,
            $controllerResult,
            new ControllerArgumentsMetadata(
                $controllerEvent,
                new ControllerArgumentsEvent($kernel, $controller, [], $request, $requestType),
            ),
        );

        $listener = new SerializeControllerResultAttributeListener($serializer);
        $listener->onView(new ControllerAttributeEvent($attributes[0], $viewEvent));

        $response = $viewEvent->getResponse();

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($responseBody, $response->getContent());
    }

    public function testMethodLevelAttributeTakesPrecedenceOverClassLevel()
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $controller = new BothLevelsSerializeController();
        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $controllerResult = new ProductCreated(10);
        $responseBody = '{"productId": 10}';

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($controllerResult, 'json', ['level' => 'method'])
            ->willReturn($responseBody);

        $controllerEvent = new ControllerEvent($kernel, $controller, $request, $requestType);
        $viewEvent = new ViewEvent(
            $kernel,
            $request,
            $requestType,
            $controllerResult,
            new ControllerArgumentsMetadata(
                $controllerEvent,
                new ControllerArgumentsEvent($kernel, $controller, [], $request, $requestType),
            ),
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SerializeControllerResultAttributeListener($serializer));

        $listener = new ControllerAttributesListener([KernelEvents::VIEW => [Serialize::class => true]]);
        $listener->afterController($viewEvent, KernelEvents::VIEW, $dispatcher);

        $response = $viewEvent->getResponse();

        self::assertSame(202, $response->getStatusCode());
        self::assertSame($responseBody, $response->getContent());
    }

    public function testMapControllerResult()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(new ProductView(10), 'json', [])
            ->willReturn('{"productId": 10}');

        $viewEvent = $this->createViewEvent(new ProductCreated(10));

        $listener = new SerializeControllerResultAttributeListener($serializer, new ObjectMapper());
        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class), $viewEvent));

        self::assertSame('{"productId": 10}', $viewEvent->getResponse()->getContent());
    }

    public function testMapEachItemOfAnArrayControllerResult()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(['a' => new ProductView(1), 'b' => null, 'c' => new ProductView(3)], 'json', [])
            ->willReturn('{}');

        $viewEvent = $this->createViewEvent(['a' => new ProductCreated(1), 'b' => null, 'c' => new ProductCreated(3)]);

        $listener = new SerializeControllerResultAttributeListener($serializer, new ObjectMapper());
        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class), $viewEvent));

        self::assertSame('{}', $viewEvent->getResponse()->getContent());
    }

    public function testMapEachItemOfAGeneratorControllerResultLazily()
    {
        $started = false;
        $controllerResult = (static function () use (&$started) {
            $started = true;
            yield 'a' => new ProductCreated(1);
            yield 'b' => new ProductCreated(2);
        })();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->willReturnCallback(static function (mixed $data) use (&$started) {
                self::assertFalse($started);
                self::assertInstanceOf(\Generator::class, $data);
                self::assertEquals(['a' => new ProductView(1), 'b' => new ProductView(2)], iterator_to_array($data));

                return '{}';
            });

        $viewEvent = $this->createViewEvent($controllerResult);

        $listener = new SerializeControllerResultAttributeListener($serializer, new ObjectMapper());
        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class), $viewEvent));

        self::assertSame('{}', $viewEvent->getResponse()->getContent());
    }

    public function testMapRequiresTheObjectMapper()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10));

        $listener = new SerializeControllerResultAttributeListener($this->createStub(SerializerInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "symfony/object-mapper" component is required to use the "map" option of the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute.');

        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class), $viewEvent));
    }

    public function testStreamControllerResult()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'show']);

        $listener = new SerializeControllerResultAttributeListener(null, null, JsonStreamWriter::create([], $this->streamWritersDir));
        $listener->onView(new ControllerAttributeEvent(new Serialize(201, ['X-Test-Header' => 'abc'], stream: true), $viewEvent));

        $response = $viewEvent->getResponse();

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('abc', $response->headers->get('X-Test-Header'));
        self::assertSame('{"productId":10}', self::getStreamedContent($response));
    }

    public function testStreamControllerResultMappedToAClass()
    {
        $controllerResult = (static function () {
            yield new ProductCreated(1);
            yield new ProductCreated(2);
        })();
        $viewEvent = $this->createViewEvent($controllerResult, [new StreamApiController(), 'untyped']);

        $listener = new SerializeControllerResultAttributeListener(null, new ObjectMapper(), JsonStreamWriter::create([], $this->streamWritersDir));
        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class, stream: true), $viewEvent));

        self::assertSame('[{"productId":1},{"productId":2}]', self::getStreamedContent($viewEvent->getResponse()));
    }

    public function testStreamNullControllerResultMappedToAClass()
    {
        $viewEvent = $this->createViewEvent(null, [new StreamApiController(), 'untyped']);

        $listener = new SerializeControllerResultAttributeListener(null, new ObjectMapper(), JsonStreamWriter::create([], $this->streamWritersDir));
        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class, stream: true), $viewEvent));

        self::assertSame('null', self::getStreamedContent($viewEvent->getResponse()));
    }

    public function testStreamRequiresTheTypeOfTheControllerResult()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'untyped']);

        $listener = new SerializeControllerResultAttributeListener(null, null, JsonStreamWriter::create([], $this->streamWritersDir));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "stream" option of the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute requires the "map" option or a return type on the controller:');

        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));
    }

    #[DataProvider('provideStreamedControllers')]
    public function testStreamCachesTheReturnTypeOfTheController(callable $controller)
    {
        $cache = new ArrayAdapter();
        $streamWriter = JsonStreamWriter::create([], $this->streamWritersDir);

        $viewEvent = $this->createViewEvent([new ProductCreated(1)], $controller);
        $listener = new SerializeControllerResultAttributeListener(null, null, $streamWriter, TypeResolver::create(), $cache);
        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));

        self::assertSame('[{"productId":1}]', self::getStreamedContent($viewEvent->getResponse()));

        $viewEvent = $this->createViewEvent([new ProductCreated(2)], $controller);
        $listener = new SerializeControllerResultAttributeListener(null, null, $streamWriter, fn () => $this->fail('The type resolver should not be resolved.'), $cache);
        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));

        self::assertSame('[{"productId":2}]', self::getStreamedContent($viewEvent->getResponse()));
    }

    public static function provideStreamedControllers(): iterable
    {
        yield 'method' => [[new StreamApiController(), 'list']];
        yield 'invokable' => [new InvokableStreamApiController()];
    }

    public function testStreamDoesNotCacheTheReturnTypeOfAnAnonymousFunction()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), static fn (): ProductCreated => new ProductCreated(10));

        $listener = new SerializeControllerResultAttributeListener(null, null, JsonStreamWriter::create([], $this->streamWritersDir), TypeResolver::create(), fn () => $this->fail('The type cache should not be resolved.'));
        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));

        self::assertSame('{"productId":10}', self::getStreamedContent($viewEvent->getResponse()));
    }

    public function testStreamDoesNotCacheAnUnsupportedReturnType()
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());
        $cache->expects($this->never())->method('save');

        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'untyped']);

        $listener = new SerializeControllerResultAttributeListener(null, null, JsonStreamWriter::create([], $this->streamWritersDir), TypeResolver::create(), $cache);

        try {
            $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));
            self::fail('The listener should have thrown.');
        } catch (\LogicException $e) {
            self::assertStringStartsWith('The "stream" option of the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute requires the "map" option or a return type on the controller:', $e->getMessage());
        }
    }

    public function testStreamRequiresTheJsonFormat()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'show']);
        $viewEvent->getRequest()->setRequestFormat('xml');

        $listener = new SerializeControllerResultAttributeListener(null, null, JsonStreamWriter::create([], $this->streamWritersDir));

        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $this->expectExceptionMessage('Unsupported format "xml".');

        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));
    }

    public function testStreamRequiresTheJsonStreamer()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'show']);

        $listener = new SerializeControllerResultAttributeListener($this->createStub(SerializerInterface::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "symfony/json-streamer" component is required to use the "stream" option of the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute.');

        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));
    }

    public function testSerializeDoesNotResolveTheStreamingDependencies()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(new ProductView(10), 'json', [])
            ->willReturn('{"productId": 10}');

        $viewEvent = $this->createViewEvent(new ProductCreated(10));
        $unexpected = fn () => $this->fail('The dependency should not be resolved.');

        $listener = new SerializeControllerResultAttributeListener(static fn () => $serializer, static fn () => new ObjectMapper(), $unexpected, $unexpected);
        $listener->onView(new ControllerAttributeEvent(new Serialize(map: ProductView::class), $viewEvent));

        self::assertSame('{"productId": 10}', $viewEvent->getResponse()->getContent());
    }

    public function testStreamDoesNotResolveTheSerializer()
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'show']);
        $unexpected = fn () => $this->fail('The dependency should not be resolved.');

        $listener = new SerializeControllerResultAttributeListener($unexpected, $unexpected, fn () => JsonStreamWriter::create([], $this->streamWritersDir), static fn () => TypeResolver::create());
        $listener->onView(new ControllerAttributeEvent(new Serialize(stream: true), $viewEvent));

        self::assertSame('{"productId":10}', self::getStreamedContent($viewEvent->getResponse()));
    }

    #[DataProvider('provideMissingDependencies')]
    public function testDependencyResolvedToNull(Serialize $attribute, bool $withSerializer, string $expectedMessage)
    {
        $viewEvent = $this->createViewEvent(new ProductCreated(10), [new StreamApiController(), 'show']);
        $serializer = $withSerializer ? $this->createStub(SerializerInterface::class) : null;
        $missing = static fn () => null;

        $listener = new SerializeControllerResultAttributeListener(static fn () => $serializer, $missing, $missing, $missing);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        $listener->onView(new ControllerAttributeEvent($attribute, $viewEvent));
    }

    public static function provideMissingDependencies(): iterable
    {
        yield 'serializer' => [new Serialize(), false, 'The "symfony/serializer" component is required to use the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute.'];
        yield 'object mapper' => [new Serialize(map: ProductView::class), true, 'The "symfony/object-mapper" component is required to use the "map" option of the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute.'];
        yield 'stream writer' => [new Serialize(stream: true), false, 'The "symfony/json-streamer" component is required to use the "stream" option of the "#[Symfony\Component\HttpKernel\Attribute\Serialize]" attribute.'];
    }

    private function createViewEvent(mixed $controllerResult, ?callable $controller = null): ViewEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $controller ??= new GetApiController();
        $requestType = HttpKernelInterface::MAIN_REQUEST;

        return new ViewEvent(
            $kernel,
            $request,
            $requestType,
            $controllerResult,
            new ControllerArgumentsMetadata(
                new ControllerEvent($kernel, $controller, $request, $requestType),
                new ControllerArgumentsEvent($kernel, $controller, [], $request, $requestType),
            ),
        );
    }

    private static function getStreamedContent(Response $response): string
    {
        $content = '';
        ob_start(static function (string $chunk) use (&$content) {
            $content .= $chunk;

            return '';
        });

        try {
            $response->sendContent();
        } finally {
            ob_end_clean();
        }

        return $content;
    }
}

class ProductCreated
{
    public function __construct(public readonly int $productId)
    {
    }
}

class ProductView
{
    public function __construct(public readonly int $productId)
    {
    }
}

class GetApiController
{
    #[Serialize(201, ['X-Test-Header' => 'abc'], ['foo' => 'bar'])]
    public function __invoke(): ProductCreated
    {
        return new ProductCreated(10);
    }
}

class StreamApiController
{
    public function show(): ProductCreated
    {
        return new ProductCreated(10);
    }

    public function untyped()
    {
        return new ProductCreated(10);
    }

    /**
     * @return list<ProductCreated>
     */
    public function list(): array
    {
        return [new ProductCreated(10)];
    }
}

class InvokableStreamApiController
{
    /**
     * @return list<ProductCreated>
     */
    public function __invoke(): array
    {
        return [new ProductCreated(10)];
    }
}

#[Serialize(201, context: ['foo' => 'bar'])]
class ClassLevelSerializeController
{
    public function __invoke(): ProductCreated
    {
        return new ProductCreated(10);
    }
}

#[Serialize(201, context: ['level' => 'class'])]
class BothLevelsSerializeController
{
    #[Serialize(202, context: ['level' => 'method'])]
    public function __invoke(): ProductCreated
    {
        return new ProductCreated(10);
    }
}
