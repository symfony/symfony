<?php

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExceptionManager.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionManager
{
    protected $exception;
    protected $request;
    protected $logger;
    protected $currentContent;

    public function __construct(\Exception $exception, Request $request, DebugLoggerInterface $logger = null)
    {
        $this->exception = $exception;
        $this->request = $request;
        $this->logger = $logger;

        $this->currentContent = '';
        while (false !== $content = ob_get_clean()) {
            $this->currentContent .= $content;
        }
    }

    public function getException()
    {
        return $this->exception;
    }

    public function getCurrentContent()
    {
        return $this->currentContent;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getLogs()
    {
        return null === $this->logger ? array() : $this->logger->getLogs();
    }

    public function countErrors()
    {
        if (null === $this->logger) {
            return 0;
        }

        $errors = 0;
        foreach ($this->logger->getLogs() as $log) {
            if ('ERR' === $log['priorityName']) {
                ++$errors;
            }
        }

        return $errors;
    }

    public function getFormat()
    {
        $format = $this->request->getRequestFormat();

        // when using CLI, we force the format to be TXT
        if (0 === strncasecmp(PHP_SAPI, 'cli', 3)) {
            $format = 'txt';
        }

        return $format;
    }

    public function getStatusCode()
    {
        return $this->exception instanceof HttpException ? $this->exception->getCode() : 500;
    }

    public function getStatusText()
    {
        return Response::$statusTexts[$this->getStatusCode()];
    }

    public function getMessage()
    {
        return null === $this->exception->getMessage() ? 'n/a' : $this->exception->getMessage();
    }

    public function getName()
    {
        return get_class($this->exception);
    }

    /**
     * Returns an array of exception traces.
     *
     * @return array An array of traces
     */
    public function getTraces()
    {
        $traces = array();
        $traces[] = array(
            'class'    => '',
            'type'     => '',
            'function' => '',
            'file'     => $this->exception->getFile(),
            'line'     => $this->exception->getLine(),
            'args'     => array(),
        );
        foreach ($this->exception->getTrace() as $entry) {
            $traces[] = array(
                'class'    => isset($entry['class']) ? $entry['class'] : '',
                'type'     => isset($entry['type']) ? $entry['type'] : '',
                'function' => $entry['function'],
                'file'     => isset($entry['file']) ? $entry['file'] : null,
                'line'     => isset($entry['line']) ? $entry['line'] : null,
                'args'     => isset($entry['args']) ? $entry['args'] : array(),
            );
        }

        return $traces;
    }
}
