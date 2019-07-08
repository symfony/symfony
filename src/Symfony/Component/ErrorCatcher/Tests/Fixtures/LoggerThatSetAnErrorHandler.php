<?php

namespace Symfony\Component\ErrorCatcher\Tests\Fixtures;

use Psr\Log\AbstractLogger;

class LoggerThatSetAnErrorHandler extends AbstractLogger
{
    private $logs = [];

    public function log($level, $message, array $context = [])
    {
        set_error_handler('is_string');
        $this->logs[] = [$level, $message, $context];
        restore_error_handler();
    }

    public function cleanLogs(): array
    {
        $logs = $this->logs;
        $this->logs = [];

        return $logs;
    }
}
