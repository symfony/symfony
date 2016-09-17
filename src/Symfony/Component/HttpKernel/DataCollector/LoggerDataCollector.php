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

use Symfony\Component\Debug\Exception\SilencedErrorContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * LogDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LoggerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $logger;

    public function __construct($logger = null)
    {
        if (null !== $logger && $logger instanceof DebugLoggerInterface) {
            $this->logger = $logger;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // everything is done as late as possible
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        if (null !== $this->logger) {
            $this->data = $this->computeErrorsCount();
            $this->data['logs'] = $this->sanitizeLogs($this->logger->getLogs());
        }
    }

    /**
     * Gets the logs.
     *
     * @return array An array of logs
     */
    public function getLogs()
    {
        return isset($this->data['logs']) ? $this->data['logs'] : array();
    }

    public function getPriorities()
    {
        return isset($this->data['priorities']) ? $this->data['priorities'] : array();
    }

    public function countErrors()
    {
        return isset($this->data['error_count']) ? $this->data['error_count'] : 0;
    }

    public function countDeprecations()
    {
        return isset($this->data['deprecation_count']) ? $this->data['deprecation_count'] : 0;
    }

    public function countWarnings()
    {
        return isset($this->data['warning_count']) ? $this->data['warning_count'] : 0;
    }

    public function countScreams()
    {
        return isset($this->data['scream_count']) ? $this->data['scream_count'] : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'logger';
    }

    private function sanitizeLogs($logs)
    {
        $sanitizedLogs = array();

        foreach ($logs as $log) {
            if (!$this->isSilencedOrDeprecationErrorLog($log)) {
                $log['context'] = $log['context'] ? $this->cloneVar($log['context']) : $log['context'];
                $sanitizedLogs[] = $log;

                continue;
            }

            $exception = $log['context']['exception'];
            $errorId = md5("{$exception->getSeverity()}/{$exception->getLine()}/{$exception->getFile()}".($exception instanceof \Exception ? "\0".$exception->getMessage() : ''), true);

            if (isset($sanitizedLogs[$errorId])) {
                ++$sanitizedLogs[$errorId]['errorCount'];
            } else {
                $log['context'] = $log['context'] ? $this->cloneVar($log['context']) : $log['context'];

                $log += array(
                    'errorCount' => 1,
                    'scream' => $exception instanceof SilencedErrorContext,
                );

                $sanitizedLogs[$errorId] = $log;
            }
        }

        return array_values($sanitizedLogs);
    }

    private function isSilencedOrDeprecationErrorLog(array $log)
    {
        if (!isset($log['context']['exception'])) {
            return false;
        }

        $exception = $log['context']['exception'];

        if ($exception instanceof SilencedErrorContext) {
            return true;
        }

        if ($exception instanceof \ErrorException && in_array($exception->getSeverity(), array(E_DEPRECATED, E_USER_DEPRECATED), true)) {
            return true;
        }

        return false;
    }

    private function computeErrorsCount()
    {
        $count = array(
            'error_count' => $this->logger->countErrors(),
            'deprecation_count' => 0,
            'warning_count' => 0,
            'scream_count' => 0,
            'priorities' => array(),
        );

        foreach ($this->logger->getLogs() as $log) {
            if (isset($count['priorities'][$log['priority']])) {
                ++$count['priorities'][$log['priority']]['count'];
            } else {
                $count['priorities'][$log['priority']] = array(
                    'count' => 1,
                    'name' => $log['priorityName'],
                );
            }
            if ('WARNING' === $log['priorityName']) {
                ++$count['warning_count'];
            }

            if ($this->isSilencedOrDeprecationErrorLog($log)) {
                if ($log['context']['exception'] instanceof SilencedErrorContext) {
                    ++$count['scream_count'];
                } else {
                    ++$count['deprecation_count'];
                }
            }
        }

        ksort($count['priorities']);

        return $count;
    }
}
