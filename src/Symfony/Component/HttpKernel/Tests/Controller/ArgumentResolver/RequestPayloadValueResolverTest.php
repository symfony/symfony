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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class RequestPayloadValueResolverTest extends TestCase
{
    public function testNotTypedArgument()
    {
        $resolver = new RequestPayloadValueResolver(
            new Serializer(),
            $this->createMock(ValidatorInterface::class),
        );

        $argument = new ArgumentMetadata('notTyped', null, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['HTTP_CONTENT_TYPE' => 'application/json']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Could not resolve the "$notTyped" controller argument: argument should be typed.');

        $resolver->onKernelControllerArguments($event);
    }

    public function testDefaultValueArgument()
    {
        $payload = new RequestPayload(50);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
           ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer(), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, true, $payload, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    public function testQueryDefaultValueArgument()
    {
        $payload = new RequestPayload(50);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer(), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, true, $payload, false, [
            MapQueryString::class => new MapQueryString(),
        ]);
        $request = Request::create('/', 'GET');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    public function testNullableValueArgument()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, true, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertSame([null], $event->getArguments());
    }

    public function testQueryNullableValueArgument()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]), $validator);

        $argument = new ArgumentMetadata('valid', QueryPayload::class, false, false, null, true, [
            MapQueryString::class => new MapQueryString(),
        ]);
        $request = Request::create('/', 'GET');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertSame([null], $event->getArguments());
    }

    public function testNullPayloadAndNotDefaultOrNullableArgument()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
        }
    }

    public function testQueryNullPayloadAndNotDefaultOrNullableArgument()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer([new ObjectNormalizer()]), $validator);

        $argument = new ArgumentMetadata('valid', QueryPayload::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(),
        ]);
        $request = Request::create('/', 'GET');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testWithoutValidatorAndCouldNotDenormalize()
    {
        $content = '{"price": 50, "title": ["not a string"]}';
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $resolver = new RequestPayloadValueResolver($serializer);

        $argument = new ArgumentMetadata('invalid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertInstanceOf(PartialDenormalizationException::class, $e->getPrevious());
        }
    }

    public function testValidationNotPassed()
    {
        $content = '{"price": 50.0, "title": ["not a string"]}';
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('invalid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertSame(422, $e->getStatusCode());
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type string.', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public function testValidationNotPerformedWhenPartialDenormalizationReturnsViolation()
    {
        $content = '{"password": "abc"}';
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('invalid', User::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type string.', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public function testUnsupportedMedia()
    {
        $serializer = new Serializer();

        $resolver = new RequestPayloadValueResolver($serializer);

        $argument = new ArgumentMetadata('invalid', \stdClass::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'foo/bar'], content: 'foo-bar');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertSame(415, $e->getStatusCode());
        }
    }

    public function testRequestContentValidationPassed()
    {
        $content = '{"price": 50}';
        $payload = new RequestPayload(50);
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    /**
     * @testWith [null]
     *           [[]]
     */
    public function testRequestContentWithUntypedErrors(?array $types)
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('This value was of an unexpected type.');
        $serializer = $this->createMock(SerializerDenormalizer::class);

        if (null === $types) {
            $exception = new NotNormalizableValueException('Error with no types');
        } else {
            $exception = NotNormalizableValueException::createForUnexpectedDataType('Error with no types', '', []);
        }
        $serializer->method('deserialize')->willThrowException(new PartialDenormalizationException([], [$exception]));

        $resolver = new RequestPayloadValueResolver($serializer, $this->createMock(ValidatorInterface::class));
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"price": 50}');

        $arguments = $resolver->resolve($request, new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]));
        $event = new ControllerArgumentsEvent($this->createMock(HttpKernelInterface::class), function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);
    }

    public function testQueryStringValidationPassed()
    {
        $payload = new RequestPayload(50);
        $query = ['price' => '50'];

        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(),
        ]);
        $request = Request::create('/', 'GET', $query);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    public function testQueryStringParameterTypeMismatch()
    {
        $query = ['price' => 'not a float'];

        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer([$normalizer], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('invalid', RequestPayload::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(),
        ]);

        $request = Request::create('/', 'GET', $query);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type float.', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public function testRequestInputValidationPassed()
    {
        $input = ['price' => '50'];
        $payload = new RequestPayload(50);

        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', $input);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    public function testRequestArrayDenormalization()
    {
        $input = [
            ['price' => '50'],
            ['price' => '23'],
        ];
        $payload = [
            new RequestPayload(50),
            new RequestPayload(23),
        ];

        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('prices', 'array', false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(type: RequestPayload::class),
        ]);
        $request = Request::create('/', 'POST', $input);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    public function testRequestInputTypeMismatch()
    {
        $input = ['price' => 'not a float'];

        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer([$normalizer], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('invalid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);

        $request = Request::create('/', 'POST', $input);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type float.', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public function testItThrowsOnMissingAttributeType()
    {
        $serializer = new Serializer();
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('prices', 'array', false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST');
        $request->attributes->set('_controller', 'App\Controller\SomeController::someMethod');

        $this->expectException(NearMissValueResolverException::class);
        $this->expectExceptionMessage('Please set the $type argument of the #[Symfony\Component\HttpKernel\Attribute\MapRequestPayload] attribute to the type of the objects in the expected array.');
        $resolver->resolve($request, $argument);
    }

    public function testItThrowsOnInvalidAttributeTypeUsage()
    {
        $serializer = new Serializer();
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('prices', null, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(type: RequestPayload::class),
        ]);
        $request = Request::create('/', 'POST');
        $request->attributes->set('_controller', 'App\Controller\SomeController::someMethod');

        $this->expectException(NearMissValueResolverException::class);
        $this->expectExceptionMessage('Please set its type to "array" when using argument $type of #[Symfony\Component\HttpKernel\Attribute\MapRequestPayload].');
        $resolver->resolve($request, $argument);
    }

    public function testItThrowsOnVariadicArgument()
    {
        $serializer = new Serializer();
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('variadic', RequestPayload::class, true, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Mapping variadic argument "$variadic" is not supported.');
        $resolver->resolve($request, $argument);
    }

    /**
     * @dataProvider provideMatchedFormatContext
     */
    public function testAcceptFormatPassed(mixed $acceptFormat, string $contentType, string $content)
    {
        $encoders = ['json' => new JsonEncoder(), 'xml' => new XmlEncoder()];
        $serializer = new Serializer([new ObjectNormalizer()], $encoders);
        $validator = (new ValidatorBuilder())->getValidator();
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => $contentType], content: $content);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(acceptFormat: $acceptFormat),
        ]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([new RequestPayload(50)], $event->getArguments());
    }

    public static function provideMatchedFormatContext(): iterable
    {
        yield 'configure with json as string, sends json' => [
            'acceptFormat' => 'json',
            'contentType' => 'application/json',
            'content' => '{"price": 50}',
        ];

        yield 'configure with json as array, sends json' => [
            'acceptFormat' => ['json'],
            'contentType' => 'application/json',
            'content' => '{"price": 50}',
        ];

        yield 'configure with xml as string, sends xml' => [
            'acceptFormat' => 'xml',
            'contentType' => 'application/xml',
            'content' => '<?xml version="1.0"?><request><price>50</price></request>',
        ];

        yield 'configure with xml as array, sends xml' => [
            'acceptFormat' => ['xml'],
            'contentType' => 'application/xml',
            'content' => '<?xml version="1.0"?><request><price>50</price></request>',
        ];

        yield 'configure with json or xml, sends json' => [
            'acceptFormat' => ['json', 'xml'],
            'contentType' => 'application/json',
            'content' => '{"price": 50}',
        ];

        yield 'configure with json or xml, sends xml' => [
            'acceptFormat' => ['json', 'xml'],
            'contentType' => 'application/xml',
            'content' => '<?xml version="1.0"?><request><price>50</price></request>',
        ];
    }

    /**
     * @dataProvider provideMismatchedFormatContext
     */
    public function testAcceptFormatNotPassed(mixed $acceptFormat, string $contentType, string $content, string $expectedExceptionMessage)
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->getValidator();
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => $contentType], content: $content);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(acceptFormat: $acceptFormat),
        ]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertSame(415, $e->getStatusCode());
            $this->assertSame($expectedExceptionMessage, $e->getMessage());
        }
    }

    public static function provideMismatchedFormatContext(): iterable
    {
        yield 'configure with json as string, sends xml' => [
            'acceptFormat' => 'json',
            'contentType' => 'application/xml',
            'content' => '<?xml version="1.0"?><request><price>50</price></request>',
            'expectedExceptionMessage' => 'Unsupported format, expects "json", but "xml" given.',
        ];

        yield 'configure with json as array, sends xml' => [
            'acceptFormat' => ['json'],
            'contentType' => 'application/xml',
            'content' => '<?xml version="1.0"?><request><price>50</price></request>',
            'expectedExceptionMessage' => 'Unsupported format, expects "json", but "xml" given.',
        ];

        yield 'configure with xml as string, sends json' => [
            'acceptFormat' => 'xml',
            'contentType' => 'application/json',
            'content' => '{"price": 50}',
            'expectedExceptionMessage' => 'Unsupported format, expects "xml", but "json" given.',
        ];

        yield 'configure with xml as array, sends json' => [
            'acceptFormat' => ['xml'],
            'contentType' => 'application/json',
            'content' => '{"price": 50}',
            'expectedExceptionMessage' => 'Unsupported format, expects "xml", but "json" given.',
        ];

        yield 'configure with json or xml, sends jsonld' => [
            'acceptFormat' => ['json', 'xml'],
            'contentType' => 'application/ld+json',
            'content' => '{"@context": "https://schema.org", "@type": "FakeType", "price": 50}',
            'expectedExceptionMessage' => 'Unsupported format, expects "json", "xml", but "jsonld" given.',
        ];
    }

    /**
     * @dataProvider provideValidationGroupsOnManyTypes
     */
    public function testValidationGroupsPassed(string $method, ValueResolver $attribute)
    {
        $input = ['price' => '50', 'title' => 'A long title, so the validation passes'];

        $payload = new RequestPayload(50);
        $payload->title = 'A long title, so the validation passes';

        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->enableAttributeMapping()->getValidator();
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $request = Request::create('/', $method, $input);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            $attribute::class => $attribute,
        ]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertEquals([$payload], $event->getArguments());
    }

    /**
     * @dataProvider provideValidationGroupsOnManyTypes
     */
    public function testValidationGroupsNotPassed(string $method, ValueResolver $attribute)
    {
        $input = ['price' => '50', 'title' => 'Too short'];

        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->enableAttributeMapping()->getValidator();
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            $attribute::class => $attribute,
        ]);
        $request = Request::create('/', $method, $input);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('title', $validationFailedException->getViolations()[0]->getPropertyPath());
            $this->assertSame('This value is too short. It should have 10 characters or more.', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public static function provideValidationGroupsOnManyTypes(): iterable
    {
        yield 'request payload with validation group as string' => [
            'POST',
            new MapRequestPayload(validationGroups: 'strict'),
        ];

        yield 'request payload with validation group as array' => [
            'POST',
            new MapRequestPayload(validationGroups: ['strict']),
        ];

        yield 'request payload with validation group as GroupSequence' => [
            'POST',
            new MapRequestPayload(validationGroups: new Assert\GroupSequence(['strict'])),
        ];

        yield 'query with validation group as string' => [
            'GET',
            new MapQueryString(validationGroups: 'strict'),
        ];

        yield 'query with validation group as array' => [
            'GET',
            new MapQueryString(validationGroups: ['strict']),
        ];

        yield 'query with validation group as GroupSequence' => [
            'GET',
            new MapQueryString(validationGroups: new Assert\GroupSequence(['strict'])),
        ];
    }

    public function testQueryValidationErrorCustomStatusCode()
    {
        $serializer = new Serializer([new ObjectNormalizer()], []);

        $validator = $this->createMock(ValidatorInterface::class);

        $validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList([new ConstraintViolation('Page is invalid', null, [], '', null, '')]));

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('page', QueryPayload::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(validationFailedStatusCode: 400),
        ]);
        $request = Request::create('/?page=123');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertSame(400, $e->getStatusCode());
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('Page is invalid', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public function testRequestPayloadValidationErrorCustomStatusCode()
    {
        $content = '{"price": 50, "title": ["not a string"]}';
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('invalid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(validationFailedStatusCode: 400),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(\sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertSame(400, $e->getStatusCode());
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type string.', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    /**
     * @dataProvider provideBoolArgument
     */
    public function testBoolArgumentInQueryString(mixed $expectedValue, ?string $parameterValue)
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('filtered', ObjectWithBoolArgument::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(),
        ]);
        $request = Request::create('/', 'GET', ['value' => $parameterValue]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertSame($expectedValue, $event->getArguments()[0]->value);
    }

    /**
     * @dataProvider provideBoolArgument
     */
    public function testBoolArgumentInBody(mixed $expectedValue, ?string $parameterValue)
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('filtered', ObjectWithBoolArgument::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', ['value' => $parameterValue], server: ['CONTENT_TYPE' => 'multipart/form-data']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertSame($expectedValue, $event->getArguments()[0]->value);
    }

    public static function provideBoolArgument()
    {
        yield 'default value' => [null, null];
        yield '"0"' => [false, '0'];
        yield '"false"' => [false, 'false'];
        yield '"no"' => [false, 'no'];
        yield '"off"' => [false, 'off'];
        yield '"1"' => [true, '1'];
        yield '"true"' => [true, 'true'];
        yield '"yes"' => [true, 'yes'];
        yield '"on"' => [true, 'on'];
    }

    /**
     * Boolean filtering must be disabled for content types other than form data.
     */
    public function testBoolArgumentInJsonBody()
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('filtered', ObjectWithBoolArgument::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', ['value' => 'off'], server: ['CONTENT_TYPE' => 'application/json']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertTrue($event->getArguments()[0]->value);
    }

    public function testConfigKeyForQueryString()
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('filtered', QueryPayload::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(key: 'value'),
        ]);
        $request = Request::create('/', Request::METHOD_GET, ['value' => ['page' => 1.0]]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $resolver->onKernelControllerArguments($event);

        $this->assertInstanceOf(QueryPayload::class, $event->getArguments()[0]);
        $this->assertSame(1.0, $event->getArguments()[0]->page);
    }
}

class RequestPayload
{
    #[Assert\Length(min: 10, groups: ['strict'])]
    public string $title;

    public function __construct(public readonly float $price)
    {
    }
}

interface SerializerDenormalizer extends SerializerInterface, DenormalizerInterface
{
}

class QueryPayload
{
    public function __construct(public readonly float $page)
    {
    }
}

class User
{
    public function __construct(
        #[Assert\NotBlank, Assert\Email]
        private string $email,
        #[Assert\NotBlank]
        private string $password,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}

class ObjectWithBoolArgument
{
    public function __construct(public readonly ?bool $value = null)
    {
    }
}
