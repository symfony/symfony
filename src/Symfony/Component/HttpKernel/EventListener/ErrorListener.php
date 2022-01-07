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
use Symfony\Component\Debug\Exception\FlattenException as LegacyFlattenException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ErrorListener implements EventSubscriberInterface
{
    protected $controller;
    protected $logger;
    protected $debug;
    protected $exceptionsMapping;

    public function __construct(string|object|array $controller, LoggerInterface $logger = null, bool $debug = false, array $exceptionsMapping = [])
    {
        $this->controller = $controller;
        $this->logger = $logger;
        $this->debug = $debug;
        $this->exceptionsMapping = $exceptionsMapping;
    }

    public function logKernelException(ExceptionEvent $event)
    {
        $throwable = $event->getThrowable();
        $logLevel = null;

        foreach ($this->exceptionsMapping as $class => $config) {
            if ($throwable instanceof $class && $config['log_level']) {
                $logLevel = $config['log_level'];
                break;
            }
        }

        foreach ($this->exceptionsMapping as $class => $config) {
            if (!$throwable instanceof $class || !$config['status_code']) {
                continue;
            }
            if (!$throwable instanceof HttpExceptionInterface || $throwable->getStatusCode() !== $config['status_code']) {
                $headers = $throwable instanceof HttpExceptionInterface ? $throwable->getHeaders() : [];
                $throwable = new HttpException($config['status_code'], $throwable->getMessage(), $throwable, $headers);
                $event->setThrowable($throwable);
            }
            break;
        }

        $e = FlattenException::createFromThrowable($throwable);

        $this->logException($throwable, sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', $e->getClass(), $e->getMessage(), $e->getFile(), $e->getLine()), $logLevel);
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if (null === $this->controller) {
            return;
        }

        $throwable = $event->getThrowable();
        $request = $this->duplicateRequest($throwable, $event->getRequest());

        try {
            $response = $event->getKernel()->handle($request, HttpKernelInterface::SUB_REQUEST, false);
        } catch (\Exception $e) {
            $f = FlattenException::createFromThrowable($e);

            $this->logException($e, sprintf('Exception thrown when handling an exception (%s: %s at %s line %s)', $f->getClass(), $f->getMessage(), $e->getFile(), $e->getLine()));

            $prev = $e;
            do {
                if ($throwable === $wrapper = $prev) {
                    throw $e;
                }
            } while ($prev = $wrapper->getPrevious());

            $prev = new \ReflectionProperty($wrapper instanceof \Exception ? \Exception::class : \Error::class, 'previous');
            $prev->setAccessible(true);
            $prev->setValue($wrapper, $throwable);

            throw $e;
        }

        $event->setResponse($response);

        if ($this->debug) {
            $event->getRequest()->attributes->set('_remove_csp_headers', true);
        }
    }

    public function removeCspHeader(ResponseEvent $event): void
    {
        if ($this->debug && $event->getRequest()->attributes->get('_remove_csp_headers', false)) {
            $event->getResponse()->headers->remove('Content-Security-Policy');
        }
    }

    public function onControllerArguments(ControllerArgumentsEvent $event)
    {
        $e = $event->getRequest()->attributes->get('exception');

        if (!$e instanceof \Throwable || false === $k = array_search($e, $event->getArguments(), true)) {
            return;
        }

        $r = new \ReflectionFunction(\Closure::fromCallable($event->getController()));
        $r = $r->getParameters()[$k] ?? null;

        if ($r && (!($r = $r->getType()) instanceof \ReflectionNamedType || \in_array($r->getName(), [FlattenException::class, LegacyFlattenException::class], true))) {
            $arguments = $event->getArguments();
            $arguments[$k] = FlattenException::createFromThrowable($e);
            $event->setArguments($arguments);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'onControllerArguments',
            KernelEvents::EXCEPTION => [
                ['logKernelException', 0],
                ['onKernelException', -128],
            ],
            KernelEvents::RESPONSE => ['removeCspHeader', -128],
        ];
    }

    /**
     * Logs an exception.
     */
    protected function logException(\Throwable $exception, string $message, string $logLevel = null): void
    {
        if (null !== $this->logger) {
            if (null !== $logLevel) {
                $this->logger->log($logLevel, $message, ['exception' => $exception]);
            } elseif (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
                $this->logger->critical($message, ['exception' => $exception]);
            } else {
                $this->logger->error($message, ['exception' => $exception]);
            }
        }
    }

    /**
     * Clones the request for the exception.
     */
    protected function duplicateRequest(\Throwable $exception, Request $request): Request
    {
        $attributes = [
            '_controller' => $this->controller,
            'exception' => $exception,
            'logger' => $this->logger instanceof DebugLoggerInterface ? $this->logger : null,
        ];
        $request = $request->duplicate(null, null, $attributes);
        $request->setMethod('GET');

        return $request;
    }
}
