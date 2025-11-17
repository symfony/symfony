<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ValidatorBuilder;

class UploadedFileValueResolverTest extends TestCase
{
    private const FIXTURES_BASE_PATH = __DIR__.'/../../Fixtures/Controller/ArgumentResolver/UploadedFile';

    #[DataProvider('provideContext')]
    public function testDefaults(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'foo',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);

        /** @var UploadedFile $data */
        $data = $event->getArguments()[0];

        $this->assertInstanceOf(UploadedFile::class, $data);
        $this->assertSame('file-small.txt', $data->getFilename());
        $this->assertSame(36, $data->getSize());
    }

    #[DataProvider('provideContext')]
    public function testEmpty(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'qux',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectException(HttpException::class);

        $resolver->onKernelControllerArguments($event);
    }

    #[DataProvider('provideContext')]
    public function testEmptyArrayArgument(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'qux',
            'array',
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $resolver->onKernelControllerArguments($event);
        $data = $event->getArguments()[0];

        $this->assertSame([], $data);
    }

    #[DataProvider('provideContext')]
    public function testCustomName(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile(name: 'bar');
        $argument = new ArgumentMetadata(
            'foo',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);

        /** @var UploadedFile $data */
        $data = $event->getArguments()[0];

        $this->assertInstanceOf(UploadedFile::class, $data);
        $this->assertSame('file-big.txt', $data->getFilename());
        $this->assertSame(71, $data->getSize());
    }

    #[DataProvider('provideContext')]
    public function testConstraintsWithoutViolation(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile(constraints: new Assert\File(maxSize: 100));
        $argument = new ArgumentMetadata(
            'bar',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);

        /** @var UploadedFile $data */
        $data = $event->getArguments()[0];

        $this->assertInstanceOf(UploadedFile::class, $data);
        $this->assertSame('file-big.txt', $data->getFilename());
        $this->assertSame(71, $data->getSize());
    }

    #[DataProvider('provideContext')]
    public function testConstraintsWithViolation(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile(constraints: new Assert\File(maxSize: 50));
        $argument = new ArgumentMetadata(
            'bar',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageMatches('/^The file is too large/');

        $resolver->onKernelControllerArguments($event);
    }

    #[DataProvider('provideContext')]
    public function testMultipleFilesArray(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'baz',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);

        /** @var UploadedFile[] $data */
        $data = $event->getArguments()[0];

        $this->assertCount(2, $data);
        $this->assertSame('file-small.txt', $data[0]->getFilename());
        $this->assertSame(36, $data[0]->getSize());
        $this->assertSame('file-big.txt', $data[1]->getFilename());
        $this->assertSame(71, $data[1]->getSize());
    }

    #[DataProvider('provideContext')]
    public function testMultipleFilesArrayConstraints(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile(constraints: new Assert\File(maxSize: 50));
        $argument = new ArgumentMetadata(
            'baz',
            UploadedFile::class,
            false,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageMatches('/^The file is too large/');

        $resolver->onKernelControllerArguments($event);
    }

    #[DataProvider('provideContext')]
    public function testMultipleFilesVariadic(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'baz',
            UploadedFile::class,
            true,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);

        /** @var UploadedFile[] $data */
        $data = $event->getArguments()[0];

        $this->assertCount(2, $data);
        $this->assertSame('file-small.txt', $data[0]->getFilename());
        $this->assertSame(36, $data[0]->getSize());
        $this->assertSame('file-big.txt', $data[1]->getFilename());
        $this->assertSame(71, $data[1]->getSize());
    }

    #[DataProvider('provideContext')]
    public function testMultipleFilesVariadicConstraints(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile(constraints: new Assert\File(maxSize: 50));
        $argument = new ArgumentMetadata(
            'baz',
            UploadedFile::class,
            true,
            false,
            null,
            false,
            [$attribute::class => $attribute]
        );
        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageMatches('/^The file is too large/');

        $resolver->onKernelControllerArguments($event);
    }

    #[DataProvider('provideContext')]
    public function testShouldAllowEmptyWhenNullable(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'qux',
            UploadedFile::class,
            false,
            false,
            null,
            true,
            [$attribute::class => $attribute]
        );
        /** @var HttpKernelInterface&MockObject $httpKernel */
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $event = new ControllerArgumentsEvent(
            $httpKernel,
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);
        $data = $event->getArguments()[0];

        $this->assertNull($data);
    }

    #[DataProvider('provideContext')]
    public function testShouldAllowEmptyWhenNullableArray(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'qux',
            'array',
            false,
            false,
            null,
            true,
            [$attribute::class => $attribute]
        );
        /** @var HttpKernelInterface&MockObject $httpKernel */
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $event = new ControllerArgumentsEvent(
            $httpKernel,
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);
        $data = $event->getArguments()[0];

        $this->assertNull($data);
    }

    #[DataProvider('provideContext')]
    public function testShouldAllowEmptyWhenHasDefaultValue(RequestPayloadValueResolver $resolver, Request $request)
    {
        $attribute = new MapUploadedFile();
        $argument = new ArgumentMetadata(
            'qux',
            UploadedFile::class,
            false,
            true,
            'default-value',
            false,
            [$attribute::class => $attribute]
        );
        /** @var HttpKernelInterface&MockObject $httpKernel */
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $event = new ControllerArgumentsEvent(
            $httpKernel,
            static function () {},
            $resolver->resolve($request, $argument),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $resolver->onKernelControllerArguments($event);
        $data = $event->getArguments()[0];

        $this->assertSame('default-value', $data);
    }

    public static function provideContext(): iterable
    {
        $resolver = new RequestPayloadValueResolver(
            new Serializer(),
            (new ValidatorBuilder())->getValidator()
        );
        $small = new UploadedFile(
            self::FIXTURES_BASE_PATH.'/file-small.txt',
            'file-small.txt',
            'text/plain',
            null,
            true
        );
        $big = new UploadedFile(
            self::FIXTURES_BASE_PATH.'/file-big.txt',
            'file-big.txt',
            'text/plain',
            null,
            true
        );
        $request = Request::create(
            '/',
            'POST',
            files: [
                'foo' => $small,
                'bar' => $big,
                'baz' => [$small, $big],
            ],
            server: ['HTTP_CONTENT_TYPE' => 'multipart/form-data']
        );

        yield 'standard' => [$resolver, $request];
    }
}
