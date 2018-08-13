<?php

class SimpleClass
{
    public static function staticMethod()
    {
        return 'static';
    }

    public function classMethod()
    {
        return 'class';
    }

    public function getClosure()
    {
        return function () {
            return 'closure';
        };
    }
}
