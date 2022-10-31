<?php

namespace Symfony\Bridge\Monolog;

use DateTimeZone;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Stringable;

class Monolog implements LoggerInterface
{
    protected Logger $monologLogger;
    protected array $handlers = [];
    protected array $processors = [];

    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = [],
        ?DateTimeZone $timezone = null
    ) {
        $this->monologLogger = new Logger($name, $handlers,  $processors,  $timezone);
    }

    public function pushProcessor(callable $callback): Logger
    {
        return $this->monologLogger->pushProcessor($callback);
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->emergency($message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->alert($message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->critical($message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->error($message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->warning($message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->notice($message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->info($message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->debug($message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->monologLogger->log($level, $message, $context);
    }
}
