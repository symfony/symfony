<?php

namespace Symfony\Framework\FoundationBundle\Listener;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\HttpKernel\LoggerInterface;
use Symfony\Components\HttpKernel\HttpKernelInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExceptionHandler.
 *
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionHandler
{
    protected $container;
    protected $controller;
    protected $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger = null, $controller)
    {
        $this->container = $container;
        $this->logger = $logger;

        $this->controller = $controller;
    }

    /**
     * Registers a core.exception listener.
     *
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher An EventDispatcher instance
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
            $this->logger->err(sprintf('%s (uncaught %s exception)', $exception->getMessage(), get_class($exception)));
        }

        $parameters = array(
            '_controller'     => $this->controller,
            'exception'       => $exception,
            'originalRequest' => $event->getParameter('request'),
            'logs'            => $this->container->hasService('zend.logger.writer.debug') ? $this->container->getService('zend.logger.writer.debug')->getLogs() : array(),
        );

        $request = $event->getParameter('request')->duplicate(null, null, $parameters);

        try {
            $response = $event->getSubject()->handle($request, HttpKernelInterface::FORWARDED_REQUEST, true);

            error_log(sprintf('%s: %s', get_class($exception), $exception->getMessage()));
        } catch (\Exception $e) {
            return false;
        }

        $event->setReturnValue($response);

        return true;
    }
}
