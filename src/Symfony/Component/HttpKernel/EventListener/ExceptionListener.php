<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * ExceptionListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionListener implements EventSubscriberInterface
{
    protected $controller;
    protected $logger;

    public function __construct($controller, LoggerInterface $logger = null)
    {
        $this->controller = $controller;
        $this->logger = $logger;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        static $handling;

        if (true === $handling) {
            return false;
        }

        $handling = true;

        $exception = $event->getException();
        $request = $event->getRequest();

        $this->logException($exception, sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));

        $attributes = array(
            '_controller' => $this->controller,
            'exception' => FlattenException::create($exception),
            'logger' => $this->logger instanceof DebugLoggerInterface ? $this->logger : null,
            // keep for BC -- as $format can be an argument of the controller callable
            // see src/Symfony/Bundle/TwigBundle/Controller/ExceptionController.php
            // @deprecated in 2.4, to be removed in 3.0
            'format' => $request->getRequestFormat(),
        );

        $request = $request->duplicate(null, null, $attributes);
        $request->setMethod('GET');

        try {
            $response = $event->getKernel()->handle($request, HttpKernelInterface::SUB_REQUEST, true);
        } catch (\Exception $e) {
            $this->logException($e, sprintf('Exception thrown when handling an exception (%s: %s at %s line %s)', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()), false);

            // set handling to false otherwise it wont be able to handle further more
            $handling = false;

            $wrapper = $e;

            while ($prev = $wrapper->getPrevious()) {
                if ($exception === $wrapper = $prev) {
                    throw $e;
                }
            }

            $prev = new \ReflectionProperty('Exception', 'previous');
            $prev->setAccessible(true);
            $prev->setValue($wrapper, $exception);

            throw $e;
        }

        $event->setResponse($response);

        $handling = false;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => array('onKernelException', -128),
        );
    }

    /**
     * Logs an exception.
     *
     * @param \Exception $exception The \Exception instance
     * @param string     $message   The error message to log
     * @param bool       $original  False when the handling of the exception thrown another exception
     */
    protected function logException(\Exception $exception, $message, $original = true)
    {
        $isCritical = !$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500;
        $context = array('exception' => $exception);
        if (null !== $this->logger) {
            if ($isCritical) {
                $this->logger->critical($message, $context);
            } else {
                $this->logger->error($message, $context);
            }
        } elseif (!$original || $isCritical) {
            error_log($message);
        }
    }
}
