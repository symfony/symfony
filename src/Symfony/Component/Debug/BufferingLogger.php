<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

use Psr\Log\AbstractLogger;

/**
 * A buffering logger that stacks logs for later.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class BufferingLogger extends AbstractLogger
{
    private $logs = array();

    public function log($level, $message, array $context = array())
    {
        $this->logs[] = array($level, $message, $context);
    }

    public function cleanLogs()
    {
        $logs = $this->logs;
        $this->logs = array();

        return $logs;
    }
}
