<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

class ErrorHandlerThatUsesThePreviousOne
{
    private static $previous;

    public static function register()
    {
        $handler = new static();

        self::$previous = set_error_handler([$handler, 'handleError']);

        return $handler;
    }

    public function handleError()
    {
        return \call_user_func_array(self::$previous, \func_get_args());
    }
}
