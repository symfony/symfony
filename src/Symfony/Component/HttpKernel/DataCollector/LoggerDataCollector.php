<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\ErrorHandler\Exception\SilencedErrorContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * LogDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class LoggerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $logger;
    private $containerPathPrefix;
    private $currentRequest;
    private $requestStack;

    public function __construct($logger = null, string $containerPathPrefix = null, RequestStack $requestStack = null)
    {
        if (null !== $logger && $logger instanceof DebugLoggerInterface) {
            $this->logger = $logger;
        }

        $this->containerPathPrefix = $containerPathPrefix;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->currentRequest = $this->requestStack && $this->requestStack->getMainRequest() !== $request ? $request : null;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if ($this->logger instanceof DebugLoggerInterface) {
            $this->logger->clear();
        }
        $this->data = [];
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        if (null !== $this->logger) {
            $containerDeprecationLogs = $this->getContainerDeprecationLogs();
            $this->data = $this->computeErrorsCount($containerDeprecationLogs);
            // get compiler logs later (only when they are needed) to improve performance
            $this->data['compiler_logs'] = [];
            $this->data['compiler_logs_filepath'] = $this->containerPathPrefix.'Compiler.log';
            $this->data['logs'] = $this->sanitizeLogs(array_merge($this->logger->getLogs($this->currentRequest), $containerDeprecationLogs));
            $this->data = $this->cloneVar($this->data);
        }
        $this->currentRequest = null;
    }

    public function getLogs()
    {
        return $this->data['logs'] ?? [];
    }

    public function getPriorities()
    {
        return $this->data['priorities'] ?? [];
    }

    public function countErrors()
    {
        return $this->data['error_count'] ?? 0;
    }

    public function countDeprecations()
    {
        return $this->data['deprecation_count'] ?? 0;
    }

    public function countWarnings()
    {
        return $this->data['warning_count'] ?? 0;
    }

    public function countScreams()
    {
        return $this->data['scream_count'] ?? 0;
    }

    public function getCompilerLogs()
    {
        return $this->cloneVar($this->getContainerCompilerLogs($this->data['compiler_logs_filepath'] ?? null));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'logger';
    }

    private function getContainerDeprecationLogs(): array
    {
        if (null === $this->containerPathPrefix || !is_file($file = $this->containerPathPrefix.'Deprecations.log')) {
            return [];
        }

        if ('' === $logContent = trim(file_get_contents($file))) {
            return [];
        }

        $bootTime = filemtime($file);
        $logs = [];
        foreach (unserialize($logContent) as $log) {
            $log['context'] = ['exception' => new SilencedErrorContext($log['type'], $log['file'], $log['line'], $log['trace'], $log['count'])];
            $log['timestamp'] = $bootTime;
            $log['priority'] = 100;
            $log['priorityName'] = 'DEBUG';
            $log['channel'] = null;
            $log['scream'] = false;
            unset($log['type'], $log['file'], $log['line'], $log['trace'], $log['trace'], $log['count']);
            $logs[] = $log;
        }

        return $logs;
    }

    private function getContainerCompilerLogs(string $compilerLogsFilepath = null): array
    {
        if (!is_file($compilerLogsFilepath)) {
            return [];
        }

        $logs = [];
        foreach (file($compilerLogsFilepath, \FILE_IGNORE_NEW_LINES) as $log) {
            $log = explode(': ', $log, 2);
            if (!isset($log[1]) || !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)++$/', $log[0])) {
                $log = ['Unknown Compiler Pass', implode(': ', $log)];
            }

            $logs[$log[0]][] = ['message' => $log[1]];
        }

        return $logs;
    }

    private function sanitizeLogs(array $logs)
    {
        $sanitizedLogs = [];
        $silencedLogs = [];

        foreach ($logs as $log) {
            if (!$this->isSilencedOrDeprecationErrorLog($log)) {
                $sanitizedLogs[] = $log;

                continue;
            }

            $message = '_'.$log['message'];
            $exception = $log['context']['exception'];

            if ($exception instanceof SilencedErrorContext) {
                if (isset($silencedLogs[$h = spl_object_hash($exception)])) {
                    continue;
                }
                $silencedLogs[$h] = true;

                if (!isset($sanitizedLogs[$message])) {
                    $sanitizedLogs[$message] = $log + [
                        'errorCount' => 0,
                        'scream' => true,
                    ];
                }
                $sanitizedLogs[$message]['errorCount'] += $exception->count;

                continue;
            }

            $errorId = md5("{$exception->getSeverity()}/{$exception->getLine()}/{$exception->getFile()}\0{$message}", true);

            if (isset($sanitizedLogs[$errorId])) {
                ++$sanitizedLogs[$errorId]['errorCount'];
            } else {
                $log += [
                    'errorCount' => 1,
                    'scream' => false,
                ];

                $sanitizedLogs[$errorId] = $log;
            }
        }

        return array_values($sanitizedLogs);
    }

    private function isSilencedOrDeprecationErrorLog(array $log): bool
    {
        if (!isset($log['context']['exception'])) {
            return false;
        }

        $exception = $log['context']['exception'];

        if ($exception instanceof SilencedErrorContext) {
            return true;
        }

        if ($exception instanceof \ErrorException && \in_array($exception->getSeverity(), [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
            return true;
        }

        return false;
    }

    private function computeErrorsCount(array $containerDeprecationLogs): array
    {
        $silencedLogs = [];
        $count = [
            'error_count' => $this->logger->countErrors($this->currentRequest),
            'deprecation_count' => 0,
            'warning_count' => 0,
            'scream_count' => 0,
            'priorities' => [],
        ];

        foreach ($this->logger->getLogs($this->currentRequest) as $log) {
            if (isset($count['priorities'][$log['priority']])) {
                ++$count['priorities'][$log['priority']]['count'];
            } else {
                $count['priorities'][$log['priority']] = [
                    'count' => 1,
                    'name' => $log['priorityName'],
                ];
            }
            if ('WARNING' === $log['priorityName']) {
                ++$count['warning_count'];
            }

            if ($this->isSilencedOrDeprecationErrorLog($log)) {
                $exception = $log['context']['exception'];
                if ($exception instanceof SilencedErrorContext) {
                    if (isset($silencedLogs[$h = spl_object_hash($exception)])) {
                        continue;
                    }
                    $silencedLogs[$h] = true;
                    $count['scream_count'] += $exception->count;
                } else {
                    ++$count['deprecation_count'];
                }
            }
        }

        foreach ($containerDeprecationLogs as $deprecationLog) {
            $count['deprecation_count'] += $deprecationLog['context']['exception']->count;
        }

        ksort($count['priorities']);

        return $count;
    }
}
