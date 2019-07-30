<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

use Symfony\Component\ErrorHandler\BufferingLogger;

class LoggerThatSetAnErrorHandler extends BufferingLogger
{
    public function log($level, $message, array $context = []): void
    {
        set_error_handler('is_string');
        parent::log($level, $message, $context);
        restore_error_handler();
    }
}
