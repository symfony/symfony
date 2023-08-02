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

use Monolog\DateTimeImmutable;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger as BaseLogger;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Logger implements a PSR-3 logger with psr/log and forwards logs to a Monolog
 * instance.
 *
 * I has the same features a Monolog, and can be used as a drop in replacement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class Monolog implements LoggerInterface, DebugLoggerInterface, ResettableInterface, ResetInterface
{
    use LoggerTrait;

    /**
     * Mapping between levels numbers defined in RFC 5424 and Monolog ones.
     *
     * @phpstan-var array<int, Level> $rfc_5424_levels
     */
    private const RFC_5424_LEVELS = [
        7 => Level::Debug,
        6 => Level::Info,
        5 => Level::Notice,
        4 => Level::Warning,
        3 => Level::Error,
        2 => Level::Critical,
        1 => Level::Alert,
        0 => Level::Emergency,
    ];

    private BaseLogger $monolog;

    /**
     * @param string             $name       The logging channel, a simple descriptive name that is attached to all log records
     * @param HandlerInterface[] $handlers   optional stack of handlers, the first one in the array is called first, etc
     * @param callable[]         $processors Optional array of processors
     * @param \DateTimeZone|null $timezone   Optional timezone, if not provided date_default_timezone_get() will be used
     *
     * @phpstan-param array<(callable(LogRecord): LogRecord)|ProcessorInterface> $processors
     */
    public function __construct(string $name, array $handlers = [], array $processors = [], \DateTimeZone $timezone = null)
    {
        if (BaseLogger::API < 3) {
            throw new \LogicException(sprintf('Monolog "%s" is not supported. Please upgrade to Monolog 3.x or higher.', BaseLogger::API));
        }

        $this->monolog = new BaseLogger($name, $handlers, $processors, $timezone);
    }

    public function getLogs(Request $request = null): array
    {
        if ($logger = $this->getDebugLogger()) {
            return $logger->getLogs($request);
        }

        return [];
    }

    public function countErrors(Request $request = null): int
    {
        if ($logger = $this->getDebugLogger()) {
            return $logger->countErrors($request);
        }

        return 0;
    }

    public function clear(): void
    {
        if ($logger = $this->getDebugLogger()) {
            $logger->clear();
        }
    }

    public function reset(): void
    {
        $this->clear();

        if ($this->monolog instanceof ResettableInterface) {
            $this->monolog->reset();
        }
    }

    public function removeDebugLogger(): void
    {
        $processors = $this->monolog->getProcessors();
        foreach ($processors as $k => $processor) {
            if ($processor instanceof DebugLoggerInterface) {
                unset($processors[$k]);
            }
        }

        $handlers = $this->monolog->getHandlers();
        foreach ($handlers as $k => $handler) {
            if ($handler instanceof DebugLoggerInterface) {
                unset($handlers[$k]);
            }
        }

        $this->monolog->__construct($this->monolog->getName(), $handlers, $processors, $this->monolog->getTimezone());
    }

    /**
     * Returns a DebugLoggerInterface instance if one is registered with this logger.
     */
    private function getDebugLogger(): ?DebugLoggerInterface
    {
        foreach ($this->monolog->getProcessors() as $processor) {
            if ($processor instanceof DebugLoggerInterface) {
                return $processor;
            }
        }

        foreach ($this->monolog->getHandlers() as $handler) {
            if ($handler instanceof DebugLoggerInterface) {
                return $handler;
            }
        }

        return null;
    }

    public function getName(): string
    {
        return $this->monolog->getName();
    }

    /**
     * Return a new cloned instance with the name changed.
     */
    public function withName(string $name): static
    {
        $new = clone $this;
        $new->monolog = $this->monolog->withName($name);

        return $new;
    }

    /**
     * Pushes a handler on to the stack.
     *
     * @return $this
     */
    public function pushHandler(HandlerInterface $handler): self
    {
        $this->monolog->pushHandler($handler);

        return $this;
    }

    /**
     * Pops a handler from the stack.
     *
     * @throws \LogicException If empty handler stack
     */
    public function popHandler(): HandlerInterface
    {
        return $this->monolog->popHandler();
    }

    /**
     * Set handlers, replacing all existing ones.
     *
     * If a map is passed, keys will be ignored.
     *
     * @param list<HandlerInterface> $handlers
     *
     * @return $this
     */
    public function setHandlers(array $handlers): static
    {
        $this->monolog->setHandlers($handlers);

        return $this;
    }

    /**
     * @return list<HandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->monolog->getHandlers();
    }

    /**
     * Adds a processor on to the stack.
     *
     * @phpstan-param ProcessorInterface|(callable(LogRecord): LogRecord) $callback
     *
     * @return $this
     */
    public function pushProcessor(ProcessorInterface|callable $callback): static
    {
        $this->monolog->pushProcessor($callback);

        return $this;
    }

    /**
     * Removes the processor on top of the stack and returns it.
     *
     * @phpstan-return ProcessorInterface|(callable(LogRecord): LogRecord)
     *
     * @throws \LogicException If empty processor stack
     */
    public function popProcessor(): callable
    {
        return $this->monolog->popProcessor();
    }

    /**
     * @return callable[]
     *
     * @phpstan-return array<ProcessorInterface|(callable(LogRecord): LogRecord)>
     */
    public function getProcessors(): array
    {
        return $this->monolog->getProcessors();
    }

    /**
     * Control the use of microsecond resolution timestamps in the 'datetime'
     * member of new records.
     *
     * As of PHP7.1 microseconds are always included by the engine, so
     * there is no performance penalty and Monolog 2 enabled microseconds
     * by default. This function lets you disable them though in case you want
     * to suppress microseconds from the output.
     *
     * @param bool $micro True to use microtime() to create timestamps
     *
     * @return $this
     */
    public function useMicrosecondTimestamps(bool $micro): static
    {
        $this->monolog->useMicrosecondTimestamps($micro);

        return $this;
    }

    /**
     * @return $this
     */
    public function useLoggingLoopDetection(bool $detectCycles): static
    {
        $this->monolog->useLoggingLoopDetection($detectCycles);

        return $this;
    }

    /**
     * Adds a log record.
     *
     * @param int               $level    The logging level (a Monolog or RFC 5424 level)
     * @param string            $message  The log message
     * @param mixed[]           $context  The log context
     * @param DateTimeImmutable $datetime Optional log date to log into the past or future
     *
     * @return bool Whether the record has been processed
     *
     * @phpstan-param value-of<Level::VALUES>|Level $level
     */
    public function addRecord(int|Level $level, string $message, array $context = [], DateTimeImmutable $datetime = null): bool
    {
        return $this->monolog->addRecord($level, $message, $context, $datetime);
    }

    /**
     * Ends a log cycle and frees all resources used by handlers.
     *
     * Closing a Handler means flushing all buffers and freeing any open resources/handles.
     * Handlers that have been closed should be able to accept log records again and re-open
     * themselves on demand, but this may not always be possible depending on implementation.
     *
     * This is useful at the end of a request and will be called automatically on every handler
     * when they get destructed.
     */
    public function close(): void
    {
        $this->monolog->close();
    }

    /**
     * Checks whether the Logger has a handler that listens on the given level.
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function isHandling(int|string|Level $level): bool
    {
        return $this->monolog->isHandling($level);
    }

    /**
     * Set a custom exception handler that will be called if adding a new record fails.
     *
     * The Closure will receive an exception object and the record that failed to be logged
     *
     * @return $this
     */
    public function setExceptionHandler(\Closure|null $callback): static
    {
        $this->monolog->setExceptionHandler($callback);

        return $this;
    }

    public function getExceptionHandler(): \Closure|null
    {
        return $this->monolog->getExceptionHandler();
    }

    /**
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param mixed              $level   The log level (a Monolog, PSR-3 or RFC 5424 level)
     * @param string|\Stringable $message The log message
     * @param mixed[]            $context The log context
     *
     * @phpstan-param Level|LogLevel::* $level
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!$level instanceof Level) {
            if (!\is_string($level) && !\is_int($level)) {
                throw new \InvalidArgumentException('$level is expected to be a string, int or '.Level::class.' instance');
            }

            if (isset(self::RFC_5424_LEVELS[$level])) {
                $level = self::RFC_5424_LEVELS[$level];
            }

            $level = BaseLogger::toMonologLevel($level);
        }

        $this->addRecord($level, (string) $message, $context);
    }

    /**
     * Sets the timezone to be used for the timestamp of log records.
     *
     * @return $this
     */
    public function setTimezone(\DateTimeZone $tz): static
    {
        $this->monolog->setTimezone($tz);

        return $this;
    }

    /**
     * Returns the timezone to be used for the timestamp of log records.
     */
    public function getTimezone(): \DateTimeZone
    {
        return $this->monolog->getTimezone();
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return $this->monolog->__serialize();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->monolog = new BaseLogger('');
        $this->monolog->__unserialize($data);
    }
}
