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

    public function handleError($type, $message, $file, $line, $context)
    {
        return \call_user_func(self::$previous, $type, $message, $file, $line, $context);
    }
}
