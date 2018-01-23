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
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class DebugProcessor implements DebugLoggerInterface
{
    private $records = array();
    private $errorCount = 0;

    public function __invoke(array $record)
    {
        $this->records[] = array(
            'timestamp' => $record['datetime']->getTimestamp(),
            'message' => $record['message'],
            'priority' => $record['level'],
            'priorityName' => $record['level_name'],
            'context' => $record['context'],
            'channel' => isset($record['channel']) ? $record['channel'] : '',
        );
        switch ($record['level']) {
            case Logger::ERROR:
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                ++$this->errorCount;
        }

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        return $this->records;
    }

    /**
     * {@inheritdoc}
     */
    public function countErrors()
    {
        return $this->errorCount;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->records = array();
        $this->errorCount = 0;
    }
}
