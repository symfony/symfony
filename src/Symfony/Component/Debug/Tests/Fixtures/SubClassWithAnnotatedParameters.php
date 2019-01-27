<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class SubClassWithAnnotatedParameters extends ClassWithAnnotatedParameters implements InterfaceWithAnnotatedParameters
{
    use TraitWithAnnotatedParameters;

    public function fooMethod(string $foo)
    {
    }

    public function barMethod($bar = null)
    {
    }

    public function quzMethod()
    {
    }

    public function whereAmI()
    {
    }

    /**
     * @param                      $defined
     * @param Type\Does\Not\Matter $definedCallable
     */
    public function iAmHere()
    {
    }
}
