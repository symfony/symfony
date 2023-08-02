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
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ResolverNotFoundException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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

        $resolver = new RequestPayloadValueResolver(new Serializer(), $validator);

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

        $resolver = new RequestPayloadValueResolver(new Serializer(), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, true, [
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

        $resolver = new RequestPayloadValueResolver(new Serializer(), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(),
        ]);
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function testQueryNullPayloadAndNotDefaultOrNullableArgument()
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = new RequestPayloadValueResolver(new Serializer(), $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapQueryString::class => new MapQueryString(),
        ]);
        $request = Request::create('/', 'GET');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, fn () => null, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
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
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $this->assertInstanceOf(PartialDenormalizationException::class, $e->getPrevious());
        }
    }

    public function testValidationNotPassed()
    {
        $content = '{"price": 50, "title": ["not a string"]}';
        $payload = new RequestPayload(50);
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($payload)
            ->willReturn(new ConstraintViolationList([new ConstraintViolation('Test', null, [], '', null, '')]));

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
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertSame(422, $e->getStatusCode());
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type unknown.', $validationFailedException->getViolations()[0]->getMessage());
            $this->assertSame('Test', $validationFailedException->getViolations()[1]->getMessage());
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
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
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
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
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
        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
        $locator = new ServiceLocator([
            'validation_groups_resolver' => fn () => new ValidationGroupsResolver(),
        ]);
        $resolver = new RequestPayloadValueResolver($serializer, $validator, null, $locator);

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
        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
        $locator = new ServiceLocator([
            'validation_groups_resolver' => fn () => new ValidationGroupsResolver(),
        ]);
        $resolver = new RequestPayloadValueResolver($serializer, $validator, null, $locator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            $attribute::class => $attribute,
        ]);
        $request = Request::create('/', $method, $input);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $resolver->onKernelControllerArguments($event);
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
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

        yield 'request payload with validation groups resolver' => [
            'POST',
            new MapRequestPayload(validationGroupsResolver: new ValidationGroupsResolver()),
        ];

        yield 'request payload with validation groups resolver as service' => [
            'POST',
            new MapRequestPayload(validationGroupsResolver: 'validation_groups_resolver'),
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

        yield 'query with validation groups resolver' => [
            'GET',
            new MapQueryString(validationGroupsResolver: new ValidationGroupsResolver()),
        ];

        yield 'query with validation groups resolver as service' => [
            'GET',
            new MapQueryString(validationGroupsResolver: 'validation_groups_resolver'),
        ];
    }

    /**
     * @dataProvider provideValidationGroupsResolverLocator
     */
    public function testExceptionIsThrownIfValidationGroupsResolverIsNotFound(?ContainerInterface $locator, string $exceptionMessage)
    {
        $input = ['price' => '50', 'title' => 'A long title, so the validation passes'];

        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
        $resolver = new RequestPayloadValueResolver($serializer, $validator, null, $locator);

        $request = Request::create('/', 'POST', $input);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(validationGroupsResolver: 'validation_groups_resolver'),
        ]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(ResolverNotFoundException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $resolver->onKernelControllerArguments($event);
    }

    public static function provideValidationGroupsResolverLocator(): iterable
    {
        $message = 'You have requested a non-existent resolver "validation_groups_resolver".';

        yield 'No locator' => [null, $message];

        yield 'Empty locator' => [new ServiceLocator([]), $message];

        yield 'Non-empty locator' => [new ServiceLocator([
            'foo' => fn () => new \stdClass(),
            'bar' => fn () => new \stdClass(),
        ]), $message.' Did you mean one of these: "foo", "bar"?'];

        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class);
        $container->register('bar', \stdClass::class);

        yield 'Container' => [$container, $message];
    }

    public function testExceptionIsThrownIfValidationGroupsResolverIsNotACallable()
    {
        $input = ['price' => '50', 'title' => 'A long title, so the validation passes'];

        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
        $locator = new ServiceLocator([
            'validation_groups_resolver' => fn () => new \stdClass(),
        ]);
        $resolver = new RequestPayloadValueResolver($serializer, $validator, null, $locator);

        $request = Request::create('/', 'POST', $input);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            MapRequestPayload::class => new MapRequestPayload(validationGroupsResolver: 'validation_groups_resolver'),
        ]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $arguments = $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($kernel, function () {}, $arguments, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The service "validation_groups_resolver" must be a callable.');

        $resolver->onKernelControllerArguments($event);
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
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
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
        $payload = new RequestPayload(50);
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($payload)
            ->willReturn(new ConstraintViolationList([new ConstraintViolation('Test', null, [], '', null, '')]));

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
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
            $this->assertSame(400, $e->getStatusCode());
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('This value should be of type unknown.', $validationFailedException->getViolations()[0]->getMessage());
            $this->assertSame('Test', $validationFailedException->getViolations()[1]->getMessage());
        }
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

class QueryPayload
{
    public function __construct(public readonly float $page)
    {
    }
}

class ValidationGroupsResolver
{
    public function __invoke(mixed $payload, Request $request)
    {
        return 'strict';
    }
}
