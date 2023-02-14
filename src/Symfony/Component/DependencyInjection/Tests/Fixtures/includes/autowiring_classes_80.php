<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\MapDecorated;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;

class AutowireSetter
{
    #[Required]
    public function setFoo(Foo $foo): void
    {
    }
}

class AutowireWither
{
    #[Required]
    public function withFoo(Foo $foo): static
    {
        return $this;
    }
}

class AutowireProperty
{
    #[Required]
    public Foo $foo;
}

class AutowireAttribute
{
    public function __construct(
        #[Autowire(service: 'some.id')]
        public \stdClass $service,
        #[Autowire(expression: "parameter('some.parameter')")]
        public string $expression,
        #[Autowire(value: '%some.parameter%/bar')]
        public string $value,
        #[Autowire(value: '%null.parameter%')]
        public ?string $nullableValue,
        #[Autowire('@some.id')]
        public \stdClass $serviceAsValue,
        #[Autowire("@=parameter('some.parameter')")]
        public string $expressionAsValue,
        #[Autowire('bar')]
        public string $rawValue,
        #[Autowire('@@bar')]
        public string $escapedRawValue,
        #[Autowire(service: 'invalid.id')]
        public ?\stdClass $invalid = null,
    ) {
    }
}

interface AsDecoratorInterface
{
}

class AsDecoratorFoo implements AsDecoratorInterface
{
}

#[AsDecorator(decorates: AsDecoratorFoo::class, priority: 10)]
class AsDecoratorBar10 implements AsDecoratorInterface
{
    public function __construct(string $arg1, #[MapDecorated] AsDecoratorInterface $inner)
    {
    }
}

#[AsDecorator(decorates: AsDecoratorFoo::class, priority: 20)]
class AsDecoratorBar20 implements AsDecoratorInterface
{
    public function __construct(string $arg1, #[MapDecorated] AsDecoratorInterface $inner)
    {
    }
}

#[AsDecorator(decorates: \NonExistent::class, onInvalid: ContainerInterface::NULL_ON_INVALID_REFERENCE)]
class AsDecoratorBaz implements AsDecoratorInterface
{
    public function __construct(#[MapDecorated] AsDecoratorInterface $inner = null)
    {
    }
}
