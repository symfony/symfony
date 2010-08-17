<?php

namespace Symfony\Bundle\ZendBundle\Logger;

use Zend\Log\Writer\AbstractWriter;
use Symfony\Components\HttpKernel\Log\DebugLoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DebugLogger.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
