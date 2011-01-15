<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;

/**
 * ExceptionListener.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionListener
{
    protected $controller;
    protected $logger;

    public function __construct($controller, LoggerInterface $logger = null)
    {
        $this->controller = $controller;
        $this->logger = $logger;
    }

    /**
     * Registers a core.exception listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.exception', array($this, 'handle'), $priority);
    }

    public function handle(Event $event)
    {
        static $handling;

        if (true === $handling) {
            return false;
        }

        $handling = true;

        $exception = $event->get('exception');
        $request = $event->get('request');

        if (null !== $this->logger) {
            $this->logger->err(sprintf('%s: %s (uncaught exception)', get_class($exception), $exception->getMessage()));
        } else {
            error_log(sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
        }

        $logger = null !== $this->logger ? $this->logger->getDebugLogger() : null;

        $attributes = array(
            '_controller' => $this->controller,
            'exception'   => FlattenException::create($exception),
            'logger'      => $logger,
            // when using CLI, we force the format to be TXT
            'format'      => 0 === strncasecmp(PHP_SAPI, 'cli', 3) ? 'txt' : $request->getRequestFormat(),
        );

        $request = $request->duplicate(null, null, $attributes);

        try {
            $response = $event->getSubject()->handle($request, HttpKernelInterface::SUB_REQUEST, true);
        } catch (\Exception $e) {
            $message = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $e->getMessage());
            if (null !== $this->logger) {
                $this->logger->err($message);
            } else {
                error_log($message);
            }

            // re-throw the exception as this is a catch-all
            throw $exception;
        }

        $event->setReturnValue($response);

        $handling = false;

        return true;
    }
}
