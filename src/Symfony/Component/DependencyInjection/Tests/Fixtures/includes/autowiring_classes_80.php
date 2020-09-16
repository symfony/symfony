<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

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
