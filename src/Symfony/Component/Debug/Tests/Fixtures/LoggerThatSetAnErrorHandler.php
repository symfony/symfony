<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

use Psr\Log\AbstractLogger;

class LoggerThatSetAnErrorHandler extends AbstractLogger
{
    public function log($level, $message, array $context = [])
    {
        set_error_handler('is_string');
        restore_error_handler();
    }
}
