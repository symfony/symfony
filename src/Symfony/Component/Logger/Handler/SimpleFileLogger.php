<?php
/**
 * @author Gonzalo Vilaseca <gonzalo.vilaseca@reiss.com>
 * @date 23/03/15
 * @copyright Copyright (c) Reiss Clothing Ltd.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace symfony\symfony\src\Symfony\Component\Logger\Handler;

use Psr\Log\LoggerInterface;

/**
 * @author Gonzalo Vilaseca <gvilaseca@reiss.co.uk>
 */
class SimpleFileLogger implements LoggerInterface
{
    protected $logfile;

    protected $eof = '\n';

    public function setLogFile($logfile)
    {
        $this->logfile = $logfile;
    }
    /**
     * {@inheritdoc}
     */
    public function emergency($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function alert($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function notice($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = array())
    {
        $this->log($message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = array())
    {
        if (!$this->logfile) {
            throw new \Exception('SimpleFileLogger must be initialized with a detination filename.');
        }
        return file_put_contents($this->logfile, $message . $this->eof);
    }
}