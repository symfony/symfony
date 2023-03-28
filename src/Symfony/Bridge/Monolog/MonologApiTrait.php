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

use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger as BaseLogger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
 */
trait MonologApiTrait
{
    protected BaseLogger $logger;

    /**
     * @see BaseLogger::$name
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    protected $name;

    /**
     * @see BaseLogger::$handlers
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     *
     * @var list<HandlerInterface>
     */
    protected $handlers;

    /**
     * @see BaseLogger::$processors
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     *
     * @var array<(callable(LogRecord): LogRecord)|ProcessorInterface>
     */
    protected $processors;

    /**
     * @see BaseLogger::$microsecondTimestamps
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    protected $microsecondTimestamps = true;

    /**
     * @see BaseLogger::$timezone
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    protected $timezone;

    /**
     * @see BaseLogger::$exceptionHandler
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    protected $exceptionHandler = null;

    /**
     * @see BaseLogger::__construct
     * @deprecated Creating an instance of Monolog\Logger through this method is deprecated. Please use ::setLogger
     */
    public function __construct() // string $name, array $handlers = [], array $processors = [], DateTimeZone|null $timezone = null
    {
        $args = \func_get_args();
        if ([] !== $args) {
            trigger_deprecation('symfony/monolog-bridge', '6.3', 'The "%s" class extending "%s" is deprecated. In the future, this class will stop extending it.', self::class, BaseLogger::class);
            $this->logger = new BaseLogger(...$args);
        }
    }

    /**
     * @see BaseLogger::getName
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function getName(): string
    {
        return $this->logger->name;
    }

    /**
     * @see BaseLogger::withName
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function withName(string $name): self
    {
        $new = clone $this;
        $new->setLogger($this->logger->withName($name));

        return $new;
    }

    /**
     * @see BaseLogger::pushHandler
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function pushHandler(HandlerInterface $handler): self
    {
        $this->logger->pushHandler($handler);

        return $this;
    }

    /**
     * @see BaseLogger::popHandler
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function popHandler(): HandlerInterface
    {
        return $this->logger->popHandler();
    }

    /**
     * @see BaseLogger::removeHandler
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function removeHandler(callable $callback): void
    {
        $this->logger->removeHandler($callback);
    }

    /**
     * @see BaseLogger::setHandlers
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function setHandlers(array $handlers): self
    {
        $this->logger->setHandlers($handlers);

        return $this;
    }

    /**
     * @see BaseLogger::getHandlers
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function getHandlers(): array
    {
        return $this->logger->getHandlers();
    }

    /**
     * @see BaseLogger::pushProcessor
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function pushProcessor(ProcessorInterface|callable $callback): self
    {
        $this->logger->pushProcessor($callback);

        return $this;
    }

    /**
     * @see BaseLogger::popProcessor
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function popProcessor(): callable
    {
        return $this->logger->popProcessor();
    }

    /**
     * @see BaseLogger::removeProcessor
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function removeProcessor(callable $callback): void
    {
        $this->logger->removeProcessor($callback);
    }

    /**
     * @see BaseLogger::getProcessors
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function getProcessors(): array
    {
        return $this->logger->getProcessors();
    }

    /**
     * @see BaseLogger::useMicrosecondTimestamps
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function useMicrosecondTimestamps(bool $micro): self
    {
        $this->logger->useMicrosecondTimestamps($micro);

        return $this;
    }

    /**
     * @see BaseLogger::useLoggingLoopDetection
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function useLoggingLoopDetection(bool $detectCycles): self
    {
        $this->logger->useLoggingLoopDetection($detectCycles);

        return $this;
    }

    /**
     * @see BaseLogger::addRecord()
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function addRecord(int|Level $level, string $message, array $context = [], DateTimeImmutable $datetime = null): bool
    {
        return $this->logger->addRecord($level, $message, $context, $datetime);
    }

    /**
     * @see BaseLogger::close
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function close(): void
    {
        $this->logger->close();
    }

    /**
     * @see BaseLogger::getLevelName
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public static function getLevelName(int|Level $level): string
    {
        return BaseLogger::getLevelName($level);
    }

    /**
     * @see BaseLogger::toMonologLevel
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public static function toMonologLevel($level): int
    {
        return BaseLogger::toMonologLevel($level);
    }

    /**
     * @see BaseLogger::isHandling
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function isHandling(int|string|Level $level): bool
    {
        return $this->logger->isHandling($level);
    }

    /**
     * @see BaseLogger::setExceptionHandler
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function setExceptionHandler(?callable $callback): self
    {
        $this->logger->setExceptionHandler($callback);

        return $this;
    }

    /**
     * @see BaseLogger::getExceptionHandler
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function getExceptionHandler(): ?callable
    {
        return $this->logger->getExceptionHandler();
    }

    /**
     * @see BaseLogger::setTimezone
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function setTimezone(DateTimeZone $tz): self
    {
        $this->logger->setTimezone($tz);

        return $this;
    }

    /**
     * @see BaseLogger::getTimezone
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->logger->getTimezone();
    }

    /**
     * @see BaseLogger::handleException
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    protected function handleException(Throwable $e, array $record): void
    {
        $this->logger->handleException($e, $record);
    }

    /**
     * @see BaseLogger::__serialize
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function __serialize(): array
    {
        return $this->logger->__serialize();
    }

    /**
     * @see BaseLogger::__unserialize
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public function __unserialize(array $data): void
    {
        $this->logger->__unserialize($data);
    }
}
