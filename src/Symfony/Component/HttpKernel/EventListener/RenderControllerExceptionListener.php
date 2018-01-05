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
use Psr\Log\NullLogger;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * RenderControllerExceptionListener renders an exception using the given controller.
 */
class RenderControllerExceptionListener implements EventSubscriberInterface
{
    protected $controller;
    protected $logger;

    public function __construct($controller, LoggerInterface $logger = null)
    {
        $this->controller = $controller;
        $this->logger = $logger ?: new NullLogger();
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => array(
                array('onKernelException', -128),
            ),
        );
    }

    public function onKernelException(GetResponseForExceptionEvent $event, string $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $exception = $event->getException();
        $request = $this->duplicateRequest($exception, $event->getRequest());
        $response = $this->handleRequest($event->getKernel(), $request, $exception);
        $this->addListenerToRemoveContentSecurityPolicyHeader($eventDispatcher);
        $event->setResponse($response);
    }

    private function duplicateRequest(\Exception $exception, Request $request): Request
    {
        $attributes = array(
            '_controller' => $this->controller,
            'exception' => FlattenException::create($exception),
            'logger' => $this->logger instanceof DebugLoggerInterface ? $this->logger : null,
        );
        $request = $request->duplicate(null, null, $attributes);
        $request->setMethod('GET');

        return $request;
    }

    private function handleRequest($kernel, $request, $exception)
    {
        try {
            return $kernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('Exception thrown when handling an exception (%s: %s at %s line %s)', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()), array('exception' => $e));
            throw $this->pushOriginalExceptionIntoNewException($exception, $e);
        }
    }

    private function addListenerToRemoveContentSecurityPolicyHeader(EventDispatcherInterface $eventDispatcher)
    {
        $cspRemovalListener = function (FilterResponseEvent $event) use (&$cspRemovalListener, $eventDispatcher) {
            $event->getResponse()->headers->remove('Content-Security-Policy');
            $eventDispatcher->removeListener(KernelEvents::RESPONSE, $cspRemovalListener);
        };
        $eventDispatcher->addListener(KernelEvents::RESPONSE, $cspRemovalListener, -128);
    }

    private function pushOriginalExceptionIntoNewException(\Exception $originalException, \Exception $newException): \Exception
    {
        $wrapper = $newException;

        while ($prev = $wrapper->getPrevious()) {
            if ($originalException === $wrapper = $prev) {
                return $newException;
            }
        }

        $prev = new \ReflectionProperty('Exception', 'previous');
        $prev->setAccessible(true);
        $prev->setValue($wrapper, $originalException);

        return $newException;
    }
}
