<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class ClassWithAnnotatedParameters
{
    /**
     * @param string $foo this is a foo parameter
     */
    public function fooMethod(string $foo)
    {
    }

    /**
     * @param string $bar parameter not implemented yet
     */
    public function barMethod(/* string $bar = null */)
    {
    }

    /**
     * @param Quz $quz parameter not implemented yet
     */
    public function quzMethod(/* Quz $quz = null */)
    {
    }

    /**
     * @param true $yes
     */
    public function isSymfony()
    {
    }

    /**
     * @param callable (string $foo) $callback a callback
     */
    public function methodWithCallback(callable $callback)
    {
    }
}
