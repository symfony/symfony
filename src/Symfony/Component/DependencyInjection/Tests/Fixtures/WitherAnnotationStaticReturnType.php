<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Tests\Compiler\FooAnnotation;

// @deprecated since Symfony 6.3, to be removed in 7.0
class WitherAnnotationStaticReturnType
{
    public $foo;

    /**
     * @required
     */
    public function withFoo(FooAnnotation $foo): static
    {
        $new = clone $this;
        $new->foo = $foo;

        return $new;
    }

    /**
     * @required
     *
     * @return $this
     */
    public function setFoo(FooAnnotation $foo): static
    {
        $this->foo = $foo;

        return $this;
    }
}
