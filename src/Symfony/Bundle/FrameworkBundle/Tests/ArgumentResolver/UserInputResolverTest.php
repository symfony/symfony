<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\ArgumentResolver\UserInputResolver;
use Symfony\Bundle\FrameworkBundle\Exception\UnparsableInputException;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation\Category;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation\DummyDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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

    public function testSupports(): void
    {
        $this->assertTrue($this->resolver->supports(new Request(), $this->createMetadata()), 'Should be supported');

        $this->assertFalse($this->resolver->supports(new Request(), $this->createMetadata(Category::class)), 'Should not be supported');
    }

    public function testResolveWithValidValue(): void
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
    public function testResolveWithInvalidValue(string $content, string $expected): void
    {
        $this->expectException($expected);
        $request = new Request(content: $content);

        iterator_to_array($this->resolver->resolve($request, $this->createMetadata()));
    }

    public function provideInvalidValues(): \Generator
    {
        yield 'Invalid value' => ['{"itMustBeTrue": false}', ValidationFailedException::class];
        yield 'Not normalizable' => ['{"randomText": ["Did", "You", "Expect", "That?"]}', UnparsableInputException::class];
    }

    private function createMetadata(string $type = DummyDto::class): ArgumentMetadata
    {
        $arguments = [
            'name' => 'foo',
            'isVariadic' => false,
            'hasDefaultValue' => false,
            'defaultValue' => null,
            'type' => $type,
        ];

        return new ArgumentMetadata(...$arguments);
    }
}
