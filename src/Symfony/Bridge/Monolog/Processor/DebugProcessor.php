<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class DebugProcessor implements DebugLoggerInterface
{
    private $records = array();
    private $errorCount = array();
    private $requestStack;

    public function __construct(RequestStack $requestStack = null)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(array $record)
    {
        $hash = $this->requestStack && ($request = $this->requestStack->getCurrentRequest()) ? spl_object_hash($request) : '';

        $this->records[$hash][] = array(
            'timestamp' => $record['datetime']->getTimestamp(),
            'message' => $record['message'],
            'priority' => $record['level'],
            'priorityName' => $record['level_name'],
            'context' => $record['context'],
            'channel' => isset($record['channel']) ? $record['channel'] : '',
        );

        if (!isset($this->errorCount[$hash])) {
            $this->errorCount[$hash] = 0;
        }

        switch ($record['level']) {
            case Logger::ERROR:
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                ++$this->errorCount[$hash];
        }

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogs(/* Request $request = null */)
    {
        if (1 <= \func_num_args() && null !== ($request = \func_get_arg(0)) && isset($this->records[$hash = spl_object_hash($request)])) {
            return $this->records[$hash];
        }

        if (0 === \count($this->records)) {
            return array();
        }

        return array_merge(...array_values($this->records));
    }

    /**
     * {@inheritdoc}
     */
    public function countErrors(/* Request $request = null */)
    {
        if (1 <= \func_num_args() && null !== ($request = \func_get_arg(0)) && isset($this->errorCount[$hash = spl_object_hash($request)])) {
            return $this->errorCount[$hash];
        }

        return array_sum($this->errorCount);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->records = array();
        $this->errorCount = array();
    }
}
