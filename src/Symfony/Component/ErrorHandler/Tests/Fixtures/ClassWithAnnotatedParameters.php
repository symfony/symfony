<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

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
}
