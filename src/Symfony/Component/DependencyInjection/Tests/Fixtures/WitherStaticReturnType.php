<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class WitherStaticReturnType
{
    public $foo;

    /**
     * @required
     */
    public function withFoo(Foo $foo): static
    {
        $new = clone $this;
        $new->foo = $foo;

        return $new;
    }

    /**
     * @required
     * @return $this
     */
    public function setFoo(Foo $foo): static
    {
        $this->foo = $foo;

        return $this;
    }
}
