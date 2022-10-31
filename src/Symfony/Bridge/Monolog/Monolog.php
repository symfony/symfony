<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Bridge\Monolog;

use DateTimeZone;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class Monolog implements LoggerInterface
{
    protected Logger $monologLogger;
    protected array $handlers = [];
    protected array $processors = [];

    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = [],
        DateTimeZone $timezone = null
    ) {
        $this->monologLogger = new Logger($name, $handlers, $processors, $timezone);
    }

    public function pushProcessor(callable $callback): Logger
    {
        return $this->monologLogger->pushProcessor($callback);
    }

    public function emergency($message, array $context = []): void
    {
        $this->monologLogger->emergency($message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->monologLogger->alert($message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->monologLogger->critical($message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->monologLogger->error($message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->monologLogger->warning($message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->monologLogger->notice($message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->monologLogger->info($message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->monologLogger->debug($message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->monologLogger->log($level, $message, $context);
    }
}
