<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle\Logger;

use Zend\Log\Writer\AbstractWriter;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * DebugLogger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DebugLogger extends AbstractWriter implements DebugLoggerInterface
{
    protected $logs = array();

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * {@inheritdoc}
     */
    public function countErrors()
    {
        $count = 0;
        foreach ($this->getLogs() as $log) {
            if ('ERR' === $log['priorityName']) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Write a message to the log.
     *
     * @param array $event Event data
     */
    protected function _write($event)
    {
        $this->logs[] = $event;
    }

    static public function factory($config = array())
    {
    }
}
