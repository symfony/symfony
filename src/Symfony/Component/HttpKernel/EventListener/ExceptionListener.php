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
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ExceptionListener implements EventSubscriberInterface
{
    protected $controller;
    protected $logger;
    protected $debug;

    public function __construct($controller, LoggerInterface $logger = null, bool $debug = false)
    {
        $this->controller = $controller;
        $this->logger = $logger;
        $this->debug = $debug;
    }

    public function logKernelException(ExceptionEvent $event)
    {
        $e = FlattenException::createFromThrowable($event->getException());

        $this->logException($event->getException(), sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', $e->getClass(), $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    public function onKernelException(ExceptionEvent $event, string $eventName = null, EventDispatcherInterface $eventDispatcher = null)
    {
        if (null === $this->controller) {
            return;
        }

        $exception = $event->getException();
        $request = $this->duplicateRequest($exception, $event->getRequest());

        try {
            $response = $event->getKernel()->handle($request, HttpKernelInterface::SUB_REQUEST, false);
        } catch (\Exception $e) {
            $f = FlattenException::createFromThrowable($e);

            $this->logException($e, sprintf('Exception thrown when handling an exception (%s: %s at %s line %s)', $f->getClass(), $f->getMessage(), $e->getFile(), $e->getLine()));

            $prev = $e;
            do {
                if ($exception === $wrapper = $prev) {
                    throw $e;
                }
            } while ($prev = $wrapper->getPrevious());

            $prev = new \ReflectionProperty($wrapper instanceof \Exception ? \Exception::class : \Error::class, 'previous');
            $prev->setAccessible(true);
            $prev->setValue($wrapper, $exception);

            throw $e;
        }

        $event->setResponse($response);

        if ($this->debug && $eventDispatcher instanceof EventDispatcherInterface) {
            $cspRemovalListener = function ($event) use (&$cspRemovalListener, $eventDispatcher) {
                $event->getResponse()->headers->remove('Content-Security-Policy');
                $eventDispatcher->removeListener(KernelEvents::RESPONSE, $cspRemovalListener);
            };
            $eventDispatcher->addListener(KernelEvents::RESPONSE, $cspRemovalListener, -128);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['logKernelException', 0],
                ['onKernelException', -128],
            ],
        ];
    }

    protected function logException(\Exception $exception, string $message)
    {
        if (null !== $this->logger) {
            if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
                $this->logger->critical($message, ['exception' => $exception]);
            } else {
                $this->logger->error($message, ['exception' => $exception]);
            }
        }
    }

    /**
     * Clones the request for the exception.
     */
    protected function duplicateRequest(\Exception $exception, Request $request): Request
    {
        $attributes = [
            '_controller' => $this->controller,
            'exception' => FlattenException::createFromThrowable($exception),
            'logger' => $this->logger instanceof DebugLoggerInterface ? $this->logger : null,
        ];
        $request = $request->duplicate(null, null, $attributes);
        $request->setMethod('GET');

        return $request;
    }
}
