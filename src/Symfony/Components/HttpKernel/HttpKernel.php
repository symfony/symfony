<?php

namespace Symfony\Components\HttpKernel;

use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\HttpKernel\Exception\NotFoundHttpException;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * HttpKernel notifies events to convert a Request object to a Response one.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HttpKernel implements HttpKernelInterface
{
    protected $dispatcher;
    protected $request;

    /**
     * Constructor
     *
     * @param EventDispatcher $dispatcher An event dispatcher instance
     */
    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Gets the Request instance associated with the main request.
     *
     * @return Request A Request instance
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Handles a request to convert it to a response.
     *
     * All exceptions are caught, and a core.exception event is notified
     * for user management.
     *
     * @param  Request $request A Request instance
     * @param  Boolean $main    Whether this is the main request or not
     * @param  Boolean $raw     Whether to catch exceptions or not
     *
     * @return Response $response A Response instance
     *
     * @throws \Exception When Exception couldn't be caught by event processing
     */
    public function handle(Request $request = null, $main = true, $raw = false)
    {
        $main = (Boolean) $main;

        if (null === $request)
        {
            $request = new Request();
        }

        if (true === $main)
        {
            $this->request = $request;
        }

        try
        {
            return $this->handleRaw($request, $main);
        }
        catch (\Exception $e)
        {
            if (true === $raw)
            {
                throw $e;
            }

            // exception
            $event = $this->dispatcher->notifyUntil(new Event($this, 'core.exception', array('main_request' => $main, 'request' => $request, 'exception' => $e)));
            if ($event->isProcessed())
            {
                return $this->filterResponse($event->getReturnValue(), $request, 'A "core.exception" listener returned a non response object.', $main);
            }

            throw $e;
        }
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param  Request $request A Request instance
     * @param  Boolean $main    Whether this is the main request or not
     *
     * @return Response $response A Response instance
     *
     * @throws \LogicException       If one of the listener does not behave as expected
     * @throws NotFoundHttpException When controller cannot be found
     */
    protected function handleRaw(Request $request, $main = true)
    {
        $main = (Boolean) $main;

        // request
        $event = $this->dispatcher->notifyUntil(new Event($this, 'core.request', array('main_request' => $main, 'request' => $request)));
        if ($event->isProcessed())
        {
            return $this->filterResponse($event->getReturnValue(), $request, 'A "core.request" listener returned a non response object.', $main);
        }

        // load controller
        $event = $this->dispatcher->notifyUntil(new Event($this, 'core.load_controller', array('main_request' => $main, 'request' => $request)));
        if (!$event->isProcessed())
        {
            throw new NotFoundHttpException('Unable to find the controller.');
        }

        list($controller, $arguments) = $event->getReturnValue();

        // controller must be a callable
        if (!is_callable($controller))
        {
            throw new \LogicException(sprintf('The controller must be a callable (%s).', var_export($controller, true)));
        }

        // controller
        $event = $this->dispatcher->notifyUntil(new Event($this, 'core.controller', array('main_request' => $main, 'request' => $request, 'controller' => &$controller, 'arguments' => &$arguments)));
        if ($event->isProcessed())
        {
            try
            {
                return $this->filterResponse($event->getReturnValue(), $request, 'A "core.controller" listener returned a non response object.', $main);
            }
            catch (\Exception $e)
            {
                $retval = $event->getReturnValue();
            }
        }
        else
        {
            // call controller
            $retval = call_user_func_array($controller, $arguments);
        }

        // view
        $event = $this->dispatcher->filter(new Event($this, 'core.view', array('main_request' => $main, 'request' => $request)), $retval);

        return $this->filterResponse($event->getReturnValue(), $request, sprintf('The controller must return a response (instead of %s).', is_object($event->getReturnValue()) ? 'an object of class '.get_class($event->getReturnValue()) : str_replace("\n", '', var_export($event->getReturnValue(), true))), $main);
    }

    /**
     * Filters a response object.
     *
     * @param Object $response A Response instance
     * @param string $message  A error message in case the response is not a Response object
     *
     * @param Object $response The filtered Response instance
     *
     * @throws \RuntimeException if the response object does not implement the send() method
     */
    protected function filterResponse($response, $request, $message, $main)
    {
        if (!$response instanceof Response)
        {
            throw new \RuntimeException($message);
        }

        $event = $this->dispatcher->filter(new Event($this, 'core.response', array('main_request' => $main, 'request' => $request)), $response);
        $response = $event->getReturnValue();

        if (!$response instanceof Response)
        {
            throw new \RuntimeException('A "core.response" listener returned a non response object.');
        }

        return $response;
    }
}
