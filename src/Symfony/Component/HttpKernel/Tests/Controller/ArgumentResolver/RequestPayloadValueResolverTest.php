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
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Could not resolve the "$notTyped" controller argument: argument should be typed.');

        $resolver->resolve($request, $argument);
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

        try {
            $resolver->resolve($request, $argument);
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

        try {
            $resolver->resolve($request, $argument);
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();
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

        try {
            $resolver->resolve($request, $argument);

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

        $this->assertEquals($payload, $resolver->resolve($request, $argument)[0]);
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

        $this->assertEquals($payload, $resolver->resolve($request, $argument)[0]);
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

        $this->assertEquals($payload, $resolver->resolve($request, $argument)[0]);
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

        $resolved = $resolver->resolve($request, $argument);

        $this->assertCount(1, $resolved);
        $this->assertEquals(new RequestPayload(50), $resolved[0]);
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

        try {
            $resolver->resolve($request, $argument);

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
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $request = Request::create('/', $method, $input);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            $attribute::class => $attribute,
        ]);

        $resolved = $resolver->resolve($request, $argument);

        $this->assertCount(1, $resolved);
        $this->assertEquals($payload, $resolved[0]);
    }

    /**
     * @dataProvider provideValidationGroupsOnManyTypes
     */
    public function testValidationGroupsNotPassed(string $method, ValueResolver $attribute)
    {
        $input = ['price' => '50', 'title' => 'Too short'];

        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
        $resolver = new RequestPayloadValueResolver($serializer, $validator);

        $argument = new ArgumentMetadata('valid', RequestPayload::class, false, false, null, false, [
            $attribute::class => $attribute,
        ]);
        $request = Request::create('/', $method, $input);

        try {
            $resolver->resolve($request, $argument);
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
}

class RequestPayload
{
    #[Assert\Length(min: 10, groups: ['strict'])]
    public string $title;

    public function __construct(public readonly float $price)
    {
    }
}
