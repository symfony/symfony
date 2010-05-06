<?php

namespace Symfony\Framework\WebBundle\Listener;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Foundation\LoggerInterface;

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
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionHandler
{
    protected $container;
    protected $bundle;
    protected $controller;
    protected $action;
    protected $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger = null, $bundle, $controller, $action)
    {
        $this->container = $container;
        $this->logger = $logger;

        $this->bundle = $bundle;
        $this->controller = $controller;
        $this->action = $action;
    }

    public function register()
    {
        $this->container->getEventDispatcherService()->connect('core.exception', array($this, 'handle'));
    }

    public function handle(Event $event)
    {
        if (!$event->getParameter('main_request'))
        {
            return false;
        }

        $exception = $event->getParameter('exception');

        if (null !== $this->logger)
        {
            $this->logger->err(sprintf('%s (uncaught %s exception)', $exception->getMessage(), get_class($exception)));
        }

        $parameters = array(
            '_bundle'         => $this->bundle,
            '_controller'     => $this->controller,
            '_action'         => $this->action,
            'exception'       => $exception,
            'originalRequest' => $event->getParameter('request'),
            'logs'            => $this->container->hasService('zend.logger.writer.debug') ? $this->container->getService('zend.logger.writer.debug')->getLogs() : array(),
        );

        $request = $event->getParameter('request')->duplicate(null, null, $parameters);

        try
        {
            $response = $event->getSubject()->handle($request, false, true);

            error_log(sprintf('%s: %s', get_class($exception), $exception->getMessage()));
        }
        catch (\Exception $e)
        {
            return false;
        }

        $event->setReturnValue($response);

        return true;
    }
}
