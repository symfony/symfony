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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    public function testValidationNotPassed()
    {
        $content = '{"price": 50}';
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
            $this->assertInstanceOf(ValidationFailedException::class, $e->getPrevious());
        }
    }

    public function testUnsupportedMedia()
    {
        $serializer = new Serializer();

        $resolver = new RequestPayloadValueResolver($serializer, null);

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
}

class RequestPayload
{
    public function __construct(public readonly float $price)
    {
    }
}
