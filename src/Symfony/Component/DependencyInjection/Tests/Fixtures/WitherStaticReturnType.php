<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Tests\Compiler\Foo;
use Symfony\Contracts\Service\Attribute\Required;

class WitherStaticReturnType
{
    public $foo;

    #[Required]
    public function withFoo(Foo $foo): static
    {
        $new = clone $this;
        $new->foo = $foo;

        return $new;
    }

    /**
     * @return $this
     */
    #[Required]
    public function setFoo(Foo $foo): static
    {
        $this->foo = $foo;

        return $this;
    }
}
