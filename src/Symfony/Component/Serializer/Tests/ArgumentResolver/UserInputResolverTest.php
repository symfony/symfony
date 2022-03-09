<?php

namespace Symfony\Component\Serializer\Tests\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Annotation\RequestBody;
use Symfony\Component\Serializer\ArgumentResolver\UserInputResolver;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\DummyDto;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

class UserInputResolverTest extends TestCase
{
    private UserInputResolver $resolver;

    protected function setUp(): void
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        $this->resolver = new UserInputResolver(new Serializer($normalizers, $encoders));
    }

    public function testSupports()
    {
        $this->assertTrue($this->resolver->supports(new Request(), $this->createMetadata()), 'Should be supported');

        $this->assertFalse($this->resolver->supports(new Request(), $this->createMetadata([])), 'Should not be supported');
    }

    public function testResolveWithValidValue()
    {
        $json = '{"randomText": "Lorem ipsum"}';
        $request = new Request(content: $json);

        $resolved = iterator_to_array($this->resolver->resolve($request, $this->createMetadata()));

        $this->assertCount(1, $resolved, 'Should resolve one argument');
        $this->assertInstanceOf(DummyDto::class, $resolved[0]);
        $this->assertSame('Lorem ipsum', $resolved[0]->randomText);
    }

    public function testResolveWithInvalidValue()
    {
        $this->expectException(PartialDenormalizationException::class);
        $request = new Request(content: '{"randomText": ["Did", "You", "Expect", "That?"]}');

        iterator_to_array($this->resolver->resolve($request, $this->createMetadata()));
    }

    private function createMetadata(?array $attributes = [new RequestBody()]): ArgumentMetadata
    {
        $arguments = [
            'name' => 'foo',
            'isVariadic' => false,
            'hasDefaultValue' => false,
            'defaultValue' => null,
            'type' => DummyDto::class,
            'attributes' => $attributes,
        ];

        return new ArgumentMetadata(...$arguments);
    }
}
