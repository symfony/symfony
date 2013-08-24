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

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * FlattenException wraps a PHP Exception to be able to serialize it.
 *
 * Basically, this class removes all objects from the trace.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.3.0
 */
class FlattenException
{
    private $message;
    private $code;
    private $previous;
    private $trace;
    private $class;
    private $statusCode;
    private $headers;
    private $file;
    private $line;

    /**
     * @since v2.3.0
     */
    public static function create(\Exception $exception, $statusCode = null, array $headers = array())
    {
        $e = new static();
        $e->setMessage($exception->getMessage());
        $e->setCode($exception->getCode());

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $headers = array_merge($headers, $exception->getHeaders());
        }

        if (null === $statusCode) {
            $statusCode = 500;
        }

        $e->setStatusCode($statusCode);
        $e->setHeaders($headers);
        $e->setTraceFromException($exception);
        $e->setClass(get_class($exception));
        $e->setFile($exception->getFile());
        $e->setLine($exception->getLine());
        if ($exception->getPrevious()) {
            $e->setPrevious(static::create($exception->getPrevious()));
        }

        return $e;
    }

    /**
     * @since v2.3.0
     */
    public function toArray()
    {
        $exceptions = array();
        foreach (array_merge(array($this), $this->getAllPrevious()) as $exception) {
            $exceptions[] = array(
                'message' => $exception->getMessage(),
                'class'   => $exception->getClass(),
                'trace'   => $exception->getTrace(),
            );
        }

        return $exceptions;
    }

    /**
     * @since v2.3.0
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @since v2.3.0
     */
    public function setStatusCode($code)
    {
        $this->statusCode = $code;
    }

    /**
     * @since v2.3.0
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @since v2.3.0
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * @since v2.3.0
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @since v2.3.0
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @since v2.3.0
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @since v2.3.0
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @since v2.3.0
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @since v2.3.0
     */
    public function setLine($line)
    {
        $this->line = $line;
    }

    /**
     * @since v2.3.0
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @since v2.3.0
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @since v2.3.0
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @since v2.3.0
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @since v2.3.0
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    /**
     * @since v2.3.0
     */
    public function setPrevious(FlattenException $previous)
    {
        $this->previous = $previous;
    }

    /**
     * @since v2.3.0
     */
    public function getAllPrevious()
    {
        $exceptions = array();
        $e = $this;
        while ($e = $e->getPrevious()) {
            $exceptions[] = $e;
        }

        return $exceptions;
    }

    /**
     * @since v2.3.0
     */
    public function getTrace()
    {
        return $this->trace;
    }

    /**
     * @since v2.3.0
     */
    public function setTraceFromException(\Exception $exception)
    {
        $trace = $exception->getTrace();

        if ($exception instanceof FatalErrorException) {
            if (function_exists('xdebug_get_function_stack')) {
                $trace = array_slice(array_reverse(xdebug_get_function_stack()), 4);

                foreach ($trace as $i => $frame) {
                    //  XDebug pre 2.1.1 doesn't currently set the call type key http://bugs.xdebug.org/view.php?id=695
                    if (!isset($frame['type'])) {
                        $trace[$i]['type'] = '??';
                    }

                    if ('dynamic' === $trace[$i]['type']) {
                        $trace[$i]['type'] = '->';
                    } elseif ('static' === $trace[$i]['type']) {
                        $trace[$i]['type'] = '::';
                    }

                    // XDebug also has a different name for the parameters array
                    if (isset($frame['params']) && !isset($frame['args'])) {
                        $trace[$i]['args'] = $frame['params'];
                        unset($trace[$i]['params']);
                    }
                }
            } else {
                $trace = array_slice(array_reverse($trace), 1);
            }
        }

        $this->setTrace($trace, $exception->getFile(), $exception->getLine());
    }

    /**
     * @since v2.3.0
     */
    public function setTrace($trace, $file, $line)
    {
        $this->trace = array();
        $this->trace[] = array(
            'namespace'   => '',
            'short_class' => '',
            'class'       => '',
            'type'        => '',
            'function'    => '',
            'file'        => $file,
            'line'        => $line,
            'args'        => array(),
        );
        foreach ($trace as $entry) {
            $class = '';
            $namespace = '';
            if (isset($entry['class'])) {
                $parts = explode('\\', $entry['class']);
                $class = array_pop($parts);
                $namespace = implode('\\', $parts);
            }

            $this->trace[] = array(
                'namespace'   => $namespace,
                'short_class' => $class,
                'class'       => isset($entry['class']) ? $entry['class'] : '',
                'type'        => isset($entry['type']) ? $entry['type'] : '',
                'function'    => isset($entry['function']) ? $entry['function'] : null,
                'file'        => isset($entry['file']) ? $entry['file'] : null,
                'line'        => isset($entry['line']) ? $entry['line'] : null,
                'args'        => isset($entry['args']) ? $this->flattenArgs($entry['args']) : array(),
            );
        }
    }

    /**
     * @since v2.3.0
     */
    private function flattenArgs($args, $level = 0)
    {
        $result = array();
        foreach ($args as $key => $value) {
            if (is_object($value)) {
                $result[$key] = array('object', get_class($value));
            } elseif (is_array($value)) {
                if ($level > 10) {
                    $result[$key] = array('array', '*DEEP NESTED ARRAY*');
                } else {
                    $result[$key] = array('array', $this->flattenArgs($value, ++$level));
                }
            } elseif (null === $value) {
                $result[$key] = array('null', null);
            } elseif (is_bool($value)) {
                $result[$key] = array('boolean', $value);
            } elseif (is_resource($value)) {
                $result[$key] = array('resource', get_resource_type($value));
            } elseif ($value instanceof \__PHP_Incomplete_Class) {
                // Special case of object, is_object will return false
                $result[$key] = array('incomplete-object', $this->getClassNameFromIncomplete($value));
            } else {
                $result[$key] = array('string', (string) $value);
            }
        }

        return $result;
    }

    /**
     * @since v2.3.0
     */
    private function getClassNameFromIncomplete(\__PHP_Incomplete_Class $value)
    {
        $array = new \ArrayObject($value);

        return $array['__PHP_Incomplete_Class_Name'];
    }
}
