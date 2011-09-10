<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * FlattenException wraps a PHP Exception to be able to serialize it.
 *
 * Basically, this class removes all objects from the trace.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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

    static public function create(\Exception $exception, $statusCode = 500, array $headers = array())
    {
        $e = new static();
        $e->setMessage($exception->getMessage());
        $e->setCode($exception->getCode());
        $e->setStatusCode($statusCode);
        $e->setHeaders($headers);
        $e->setTrace($exception->getTrace(), $exception->getFile(), $exception->getLine());
        $e->setClass(get_class($exception));
        if ($exception->getPrevious()) {
            $e->setPrevious(static::create($exception->getPrevious()));
        }

        return $e;
    }

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

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($code)
    {
        $this->statusCode = $code;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code)
    {
        $this->code = $code;
    }

    public function getPrevious()
    {
        return $this->previous;
    }

    public function setPrevious(FlattenException $previous)
    {
        $this->previous = $previous;
    }

    public function getAllPrevious()
    {
        $exceptions = array();
        $e = $this;
        while ($e = $e->getPrevious()) {
            $exceptions[] = $e;
        }

        return $exceptions;
    }

    public function getTrace()
    {
        return $this->trace;
    }

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
                'function'    => $entry['function'],
                'file'        => isset($entry['file']) ? $entry['file'] : null,
                'line'        => isset($entry['line']) ? $entry['line'] : null,
                'args'        => isset($entry['args']) ? $this->flattenArgs($entry['args']) : array(),
            );
        }
    }

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
            } else {
                $result[$key] = array('string', (string) $value);
            }
        }

        return $result;
    }
}
