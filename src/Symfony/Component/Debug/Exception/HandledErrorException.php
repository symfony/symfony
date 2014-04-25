<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Exception;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HandledErrorException extends \ErrorException
{
    private $handlerOutput = false;
    private $context = array();

    public function __construct($message, $code, $severity, $filename, $lineno, $context = array())
    {
        parent::__construct($message, $code, $severity, $filename, $lineno);
        $this->context = $context;
    }

    /**
     * @return array Array of variables that existed when the exception occurred
     */
    public function getContext()
    {
        return $this->context;
    }

    public function handleWith($exceptionHandler)
    {
        $this->handlerOutput = false;
        ob_start(array($this, 'catchOutput'));
        call_user_func($exceptionHandler, $this);
        if (false === $this->handlerOutput) {
            ob_end_clean();
        }
        ob_start(array(__CLASS__, 'flushOutput'));
        echo $this->handlerOutput;
        $this->handlerOutput = ob_get_length();
    }

    /**
     * @internal
     */
    public function catchOutput($buffer)
    {
        $this->handlerOutput = $buffer;

        return '';
    }

    /**
     * @internal
     */
    public static function flushOutput($buffer)
    {
        return $buffer;
    }

    public function cleanOutput()
    {
        $status = ob_get_status();

        if (isset($status['name']) && __CLASS__.'::flushOutput' === $status['name']) {
            if ($this->handlerOutput) {
                // use substr_replace() instead of substr() for mbstring overloading resistance
                echo substr_replace(ob_get_clean(), '', 0, $this->handlerOutput);
            } else {
                ob_end_flush();
            }
        }
    }

    public function __destruct()
    {
        $this->handlerOutput = 0;
        $this->cleanOutput();
    }
}
