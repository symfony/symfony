<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use Symfony\Component\HttpKernel\Log\LoggerInterface;

class Logger implements LoggerInterface
{
    protected $logs;

    public function __construct()
    {
        $this->clear();
    }

    public function getLogs($priority = false)
    {
        return false === $priority ? $this->logs : $this->logs[$priority];
    }

    public function clear()
    {
        $this->logs = array(
            'emerg' => array(),
            'alert' => array(),
            'crit' => array(),
            'err' => array(),
            'warn' => array(),
            'notice' => array(),
            'info' => array(),
            'debug' => array(),
        );
    }

    public function log($message, $priority)
    {
        $this->logs[$priority][] = $message;
    }

    public function emerg($message)
    {
        $this->log($message, 'emerg');
    }

    public function alert($message)
    {
        $this->log($message, 'alert');
    }

    public function crit($message)
    {
        $this->log($message, 'crit');
    }

    public function err($message)
    {
        $this->log($message, 'err');
    }

    public function warn($message)
    {
        $this->log($message, 'warn');
    }

    public function notice($message)
    {
        $this->log($message, 'notice');
    }

    public function info($message)
    {
        $this->log($message, 'info');
    }

    public function debug($message)
    {
        $this->log($message, 'debug');
    }
}
