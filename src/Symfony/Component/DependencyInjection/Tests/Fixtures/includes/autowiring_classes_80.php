<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire(service: 'invalid.id')]
        public ?\stdClass $invalid = null,
    ) {
    }
}
