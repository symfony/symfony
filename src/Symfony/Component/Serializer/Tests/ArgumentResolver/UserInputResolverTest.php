<?php

namespace Symfony\Component\Serializer\Tests\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Annotation\Input;
use Symfony\Component\Serializer\ArgumentResolver\UserInputResolver;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
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

        $this->resolver = new UserInputResolver(Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator(), new Serializer($normalizers, $encoders));
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

    /**
     * @dataProvider provideInvalidValues
     */
    public function testResolveWithInvalidValue(string $content, array $groups = ['Default'])
    {
        $this->expectException(ValidationFailedException::class);
        $request = new Request(content: $content);

        iterator_to_array($this->resolver->resolve($request, $this->createMetadata([new Input(validationGroups: $groups)])));
    }

    public function provideInvalidValues(): \Generator
    {
        yield 'Invalid value' => ['{"itMustBeTrue": false}'];
        yield 'Invalid value with groups' => ['{"randomText": "Valid"}', ['Default', 'Foo']];
        yield 'Not normalizable' => ['{"randomText": ["Did", "You", "Expect", "That?"]}'];
    }

    private function createMetadata(array $attributes = [new Input()]): ArgumentMetadata
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
