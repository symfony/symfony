<?php

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExceptionListener.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionListener
{
    protected $container;
    protected $controller;
    protected $logger;

    public function __construct(ContainerInterface $container, $controller, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->controller = $controller;
        $this->logger = $logger;
    }

    /**
     * Registers a core.exception listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.exception', array($this, 'handle'));
    }

    public function handle(Event $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return false;
        }

        $exception = $event->getParameter('exception');

        if (null !== $this->logger) {
            $this->logger->err(sprintf('%s: %s (uncaught exception)', get_class($exception), $exception->getMessage()));
        } else {
            error_log(sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
        }

        $class = $this->container->getParameter('exception_manager.class');
        $logger = $this->container->has('logger.debug') ? $this->container->get('logger.debug') : null;

        $attributes = array(
            '_controller' => $this->controller,
            'manager'     => new $class($exception, $event->getParameter('request'), $logger),
        );

        $request = $event->getParameter('request')->duplicate(null, null, $attributes);

        try {
            $response = $event->getSubject()->handle($request, HttpKernelInterface::SUB_REQUEST, true);
        } catch (\Exception $e) {
            if (null !== $this->logger) {
                $this->logger->err(sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $e->getMessage()));
            }

            return false;
        }

        $event->setReturnValue($response);

        return true;
    }
}
