<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
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

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class CustomAutowire extends Autowire
{
    public function __construct(string $parameter)
    {
        parent::__construct(param: $parameter);
    }
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
        #[CustomAutowire('some.parameter')]
        public string $customAutowire,
        #[Autowire(service: 'invalid.id')]
        public ?\stdClass $invalid = null,
    ) {
    }
}

class AutowireAttributeNullFallback
{
    public function __construct(
        #[Autowire('%required.parameter%')]
        public string $required,
        #[Autowire('%optional.parameter%')]
        public ?string $optional = null,
    ) {
    }
}

class AutowireAttributeEnv
{
    public function __construct(
        #[Autowire(env: 'ENABLED')]
        public bool $enabled,
        #[Autowire(env: 'OPTIONAL')]
        public ?string $optional = null,
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
    public function __construct(string $arg1, #[AutowireDecorated] AsDecoratorInterface $inner)
    {
    }
}

#[AsDecorator(decorates: AsDecoratorFoo::class, priority: 20)]
class AsDecoratorBar20 implements AsDecoratorInterface
{
    public function __construct(string $arg1, #[AutowireDecorated] AsDecoratorInterface $inner)
    {
    }
}

#[AsDecorator(decorates: \NonExistent::class, onInvalid: ContainerInterface::NULL_ON_INVALID_REFERENCE)]
class AsDecoratorBaz implements AsDecoratorInterface
{
    public function __construct(#[AutowireDecorated] ?AsDecoratorInterface $inner = null)
    {
    }
}

#[AsDecorator(decorates: AsDecoratorFoo::class)]
class AutowireNestedAttributes implements AsDecoratorInterface
{
    public function __construct(
        #[Autowire([
            'decorated' => new AutowireDecorated(),
            'iterator' => new AutowireIterator('foo'),
            'locator' => new AutowireLocator('foo'),
            'service' => new Autowire(service: 'bar')
        ])] array $options)
    {
    }
}

class LazyServiceAttributeAutowiring
{
    public function __construct(#[Lazy] A $a)
    {
    }
}

class LazyAutowireServiceAttributesAutowiring
{
    public function __construct(#[Lazy, Autowire(lazy: true)] A $a)
    {
    }
}

class AutowireInlineAttributesBar
{
    public function __construct(Foo $foo, string $someString)
    {
    }
}

class AutowireInlineAttributes1
{
    public function __construct(
        #[AutowireInline(AutowireInlineAttributesBar::class, [
            '$foo' => Foo::class,
            '$someString' => 'testString',
        ])]
        public AutowireInlineAttributesBar $inlined,
    ) {
    }
}

class AutowireInlineAttributes2
{
    public function __construct(
        #[AutowireInline(AutowireInlineAttributesBar::class, [
            '$someString' => 'testString',
        ])]
        public AutowireInlineAttributesBar $inlined,
    ) {
    }
}
