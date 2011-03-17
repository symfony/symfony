<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Logger;

use Monolog\Handler\TestHandler;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * DebugLogger.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugHandler extends TestHandler implements DebugLoggerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        $records = array();
        foreach ($this->records as $record) {
            $records[] = array(
                'timestamp' => $record['datetime']->getTimestamp(),
                'message' => $record['message'],
                'priority' => $record['level'],
                'priorityName' => $record['level_name'],
            );
        }
        return $records;
    }

    /**
     * {@inheritdoc}
     */
    public function countErrors()
    {
        return isset($this->recordsByLevel[\Monolog\Logger::ERROR])
            ? count($this->recordsByLevel[\Monolog\Logger::ERROR])
            : 0;
    }
}
