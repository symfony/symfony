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
    private $errorNames = array(
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_NOTICE => 'E_NOTICE',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_WARNING => 'E_WARNING',
        E_USER_WARNING => 'E_USER_WARNING',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_PARSE => 'E_PARSE',
        E_ERROR => 'E_ERROR',
        E_CORE_ERROR => 'E_CORE_ERROR',
    );

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
     * Gets the called events.
     *
     * @return array An array of called events
     *
     * @see TraceableEventDispatcherInterface
     */
    public function countErrors()
    {
        return isset($this->data['error_count']) ? $this->data['error_count'] : 0;
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

    public function countDeprecations()
    {
        return isset($this->data['deprecation_count']) ? $this->data['deprecation_count'] : 0;
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
        $errorContextById = array();
        $sanitizedLogs = array();

        foreach ($logs as $log) {
            $context = $this->sanitizeContext($log['context']);

            if (isset($context['type'], $context['file'], $context['line'], $context['level'])) {
                $errorId = md5("{$context['type']}/{$context['line']}/{$context['file']}\x00{$log['message']}", true);
                $silenced = !($context['type'] & $context['level']);
                if (isset($this->errorNames[$context['type']])) {
                    $context = array_merge(array('name' => $this->errorNames[$context['type']]), $context);
                }

                if (isset($errorContextById[$errorId])) {
                    if (isset($errorContextById[$errorId]['errorCount'])) {
                        ++$errorContextById[$errorId]['errorCount'];
                    } else {
                        $errorContextById[$errorId]['errorCount'] = 2;
                    }

                    if (!$silenced && isset($errorContextById[$errorId]['scream'])) {
                        unset($errorContextById[$errorId]['scream']);
                        $errorContextById[$errorId]['level'] = $context['level'];
                    }

                    continue;
                }

                $errorContextById[$errorId] = &$context;
                if ($silenced) {
                    $context['scream'] = true;
                }

                $log['context'] = &$context;
                unset($context);
            } else {
                $log['context'] = $context;
            }

            $sanitizedLogs[] = $log;
        }

        return $sanitizedLogs;
    }

    private function sanitizeContext($context)
    {
        if (is_array($context)) {
            foreach ($context as $key => $value) {
                $context[$key] = $this->sanitizeContext($value);
            }

            return $context;
        }

        if (is_resource($context)) {
            return sprintf('Resource(%s)', get_resource_type($context));
        }

        if (is_object($context)) {
            return sprintf('Object(%s)', get_class($context));
        }

        return $context;
    }

    private function computeErrorsCount()
    {
        $count = array(
            'error_count' => $this->logger->countErrors(),
            'deprecation_count' => 0,
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

            if (isset($log['context']['type'], $log['context']['level'])) {
                if (E_DEPRECATED === $log['context']['type'] || E_USER_DEPRECATED === $log['context']['type']) {
                    ++$count['deprecation_count'];
                } elseif (!($log['context']['type'] & $log['context']['level'])) {
                    ++$count['scream_count'];
                }
            }
        }

        ksort($count['priorities']);

        return $count;
    }
}
