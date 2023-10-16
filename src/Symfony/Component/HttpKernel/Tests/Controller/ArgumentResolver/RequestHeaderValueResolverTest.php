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
use Symfony\Component\HttpKernel\Attribute\MapRequestHeader;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestHeaderValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\ValidatorBuilder;

class RequestHeaderValueResolverTest extends TestCase
{
    private const HEADER_PARAMS = [
        'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'accept-language' => 'en-us,en;q=0.5',
        'host' => 'localhost',
        'user-agent' => 'Symfony',
    ];

    private ValueResolverInterface $resolver;

    protected function setUp(): void
    {
        $serializer = new Serializer([new ObjectNormalizer()]);
        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
        $this->resolver = new RequestHeaderValueResolver($serializer, $validator);
    }

    public function testWithStringType()
    {
        foreach (self::HEADER_PARAMS as $parameter => $value) {
            $metadata = new ArgumentMetadata('variableName', 'string', false, false, null, false, [
                MapRequestHeader::class => new MapRequestHeader($parameter),
            ]);

            $arguments = $this->resolver->resolve(Request::create('/'), $metadata);

            self::assertEquals([$value], $arguments);
        }
    }

    public function testWithArrayType()
    {
        foreach (self::HEADER_PARAMS as $parameter => $value) {
            $metadata = new ArgumentMetadata('variableName', 'array', false, false, null, false, [
                MapRequestHeader::class => new MapRequestHeader($parameter),
            ]);

            $arguments = $this->resolver->resolve(Request::create('/'), $metadata);

            self::assertEquals([explode(',', $value)], $arguments);
        }
    }

    public function testWithNoValue()
    {
        $metadata = new ArgumentMetadata('variableName', 'string', false, false, null, false, [
            MapRequestHeader::class => new MapRequestHeader(),
        ]);

        $arguments = $this->resolver->resolve(Request::create('/'), $metadata);

        self::assertEquals([null], $arguments);
    }

    public function testWithDtoAndErrorWithValidationGroups()
    {
        $request = Request::create('/');

        $argument = new ArgumentMetadata('HeaderPayload', HeaderPayloadDto::class, false, false, null, false, [
            MapRequestHeader::class => new MapRequestHeader(validationGroups: ['strict']),
        ]);

        try {
            $this->resolver->resolve($request, $argument);
            $this->fail(sprintf('Expected "%s" to be thrown.', HttpException::class));
        } catch (HttpException $e) {
            $validationFailedException = $e->getPrevious();

            $this->assertSame(422, $e->getStatusCode());
            $this->assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            $this->assertSame('host', $validationFailedException->getViolations()[0]->getPropertyPath());
            $this->assertSame('This value should be equal to "symfony.com".', $validationFailedException->getViolations()[0]->getMessage());
        }
    }

    public function testWithDtoAndDefaultValidationPassed()
    {
        $payload = new HeaderPayloadDto(
            'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'localhost'
        );

        $request = Request::create('/');

        $argument = new ArgumentMetadata('HeaderPayload', HeaderPayloadDto::class, false, false, null, false, [
            MapRequestHeader::class => new MapRequestHeader(),
        ]);

        $arguments = $this->resolver->resolve($request, $argument);

        $this->assertEquals([$payload], $arguments);
    }
}

class HeaderPayloadDto
{
    public function __construct(
        public readonly string $accept,
        #[Assert\EqualTo('symfony.com', groups: ['strict'])]
        public readonly string $host,
    ) {
    }
}
