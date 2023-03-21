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
use Monolog\Logger as BaseLogger;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Logger implements LoggerInterface, DebugLoggerInterface, ResetInterface
{
    use MonologApiTrait;

    /**
     * @see BaseLogger::DEBUG
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const DEBUG = 100;

    /**
     * @see BaseLogger::INFO
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const INFO = 200;

    /**
     * @see BaseLogger::NOTICE
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually..
     */
    public const NOTICE = 250;

    /**
     * @see BaseLogger::WARNING
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const WARNING = 300;

    /**
     * @see BaseLogger::ERROR
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const ERROR = 400;

    /**
     * @see BaseLogger::CRITICAL
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const CRITICAL = 500;

    /**
     * @see BaseLogger::ALERT
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const ALERT = 550;

    /**
     * @see BaseLogger::EMERGENCY
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const EMERGENCY = 600;

    /**
     * @see BaseLogger::API
     * @deprecated This has been copied over from \Monolog\Logger for compatibility reasons, and might be removed eventually.
     */
    public const API = 3;

    public function setLogger(BaseLogger $logger): self
    {
        $this->logger = $logger;

        return $this;
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

    /**
     * @return void
     */
    public function clear()
    {
        if ($logger = $this->getDebugLogger()) {
            $logger->clear();
        }
    }

    public function reset(): void
    {
        $this->clear();

        $this->logger->reset();
    }

    /**
     * @return void
     */
    public function removeDebugLogger()
    {
        $this->logger->removeProcessor(fn (int $key, ProcessorInterface $processor) => $processor instanceof DebugLoggerInterface);
        $this->logger->removeHandler(fn (int $key, HandlerInterface $handler) => $handler instanceof DebugLoggerInterface);
    }

    /**
     * Returns a DebugLoggerInterface instance if one is registered with this logger.
     */
    private function getDebugLogger(): ?DebugLoggerInterface
    {
        foreach ($this->logger->getProcessors() as $processor) {
            if ($processor instanceof DebugLoggerInterface) {
                return $processor;
            }
        }

        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof DebugLoggerInterface) {
                return $handler;
            }
        }

        return null;
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
