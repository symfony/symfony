<?php


namespace Symfony\Component\Profiler\ProfileData;


use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class LoggerData implements ProfileDataInterface
{
    protected $data = array();
    protected $logs;

    public function __construct(DebugLoggerInterface $logger)
    {
        $this->data = $this->computeErrorsCount($logger);
        $this->logs = $this->sanitizeLogs($logger->getLogs());
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
        return $this->logs;
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

    public function getName()
    {
        return 'logger';
    }

    private function sanitizeLogs($logs)
    {
        foreach ($logs as $i => $log) {
            $context = $this->sanitizeContext($log['context']);
            if (isset($context['type'], $context['level']) && !($context['type'] & $context['level'])) {
                $context['scream'] = true;
            }
            $logs[$i]['context'] = $context;
        }

        return $logs;
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

    private function computeErrorsCount(DebugLoggerInterface $logger)
    {
        $count = array(
            'error_count' => $logger->countErrors(),
            'deprecation_count' => 0,
            'scream_count' => 0,
            'priorities' => array(),
        );

        foreach ($logger->getLogs() as $log) {
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