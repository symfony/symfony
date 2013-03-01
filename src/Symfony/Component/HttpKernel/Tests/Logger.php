<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

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

    public function emerg($message, array $context = array())
    {
        $this->log($message, 'emerg');
    }

    public function alert($message, array $context = array())
    {
        $this->log($message, 'alert');
    }

    public function crit($message, array $context = array())
    {
        $this->log($message, 'crit');
    }

    public function err($message, array $context = array())
    {
        $this->log($message, 'err');
    }

    public function warn($message, array $context = array())
    {
        $this->log($message, 'warn');
    }

    public function notice($message, array $context = array())
    {
        $this->log($message, 'notice');
    }

    public function info($message, array $context = array())
    {
        $this->log($message, 'info');
    }

    public function debug($message, array $context = array())
    {
        $this->log($message, 'debug');
    }
}
