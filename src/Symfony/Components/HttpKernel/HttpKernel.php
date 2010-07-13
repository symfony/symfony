<?php

namespace Symfony\Components\HttpKernel;

use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Components\HttpFoundation\Request;
use Symfony\Components\HttpFoundation\Response;

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
     * Gets the Request instance associated with the master request.
     *
     * @return Request A Request instance
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * All exceptions are caught, and a core.exception event is notified
     * for user management.
     *
     * @param Request $request A Request instance
     * @param integer $type    The type of the request (one of HttpKernelInterface::MASTER_REQUEST, HttpKernelInterface::FORWARDED_REQUEST, or HttpKernelInterface::EMBEDDED_REQUEST)
     * @param Boolean $raw     Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     *                    and couldn't be caught by event processing or $raw is true
     */
    public function handle(Request $request = null, $type = HttpKernelInterface::MASTER_REQUEST, $raw = false)
    {
        if (HttpKernelInterface::EMBEDDED_REQUEST === $type) {
            return $this->handleEmbedded($request, $raw);
        }

        if (null === $request) {
            $request = new Request();
        }

        if (HttpKernelInterface::MASTER_REQUEST === $type) {
            $this->request = $request;
        }

        try {
            return $this->handleRaw($request, $type);
        } catch (\Exception $e) {
            if (true === $raw) {
                throw $e;
            }

            // exception
            $event = $this->dispatcher->notifyUntil(new Event($this, 'core.exception', array('request_type' => $type, 'request' => $request, 'exception' => $e)));
            if ($event->isProcessed()) {
                return $this->filterResponse($event->getReturnValue(), $request, 'A "core.exception" listener returned a non response object.', $type);
            }

            throw $e;
        }
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param Request $request A Request instance
     * @param integer $type    The type of the request (one of HttpKernelInterface::MASTER_REQUEST, HttpKernelInterface::FORWARDED_REQUEST, or HttpKernelInterface::EMBEDDED_REQUEST)
     *
     * @return Response A Response instance
     *
     * @throws \LogicException       If one of the listener does not behave as expected
     * @throws NotFoundHttpException When controller cannot be found
     */
    protected function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // request
        $event = $this->dispatcher->notifyUntil(new Event($this, 'core.request', array('request_type' => $type, 'request' => $request)));
        if ($event->isProcessed()) {
            return $this->filterResponse($event->getReturnValue(), $request, 'A "core.request" listener returned a non response object.', $type);
        }

        // load controller
        $event = $this->dispatcher->notifyUntil(new Event($this, 'core.load_controller', array('request_type' => $type, 'request' => $request)));
        if (!$event->isProcessed()) {
            throw new NotFoundHttpException('Unable to find the controller.');
        }

        list($controller, $arguments) = $event->getReturnValue();

        // controller must be a callable
        if (!is_callable($controller)) {
            throw new \LogicException(sprintf('The controller must be a callable (%s).', var_export($controller, true)));
        }

        // controller
        $event = $this->dispatcher->notifyUntil(new Event($this, 'core.controller', array('request_type' => $type, 'request' => $request, 'controller' => &$controller, 'arguments' => &$arguments)));
        if ($event->isProcessed()) {
            try {
                return $this->filterResponse($event->getReturnValue(), $request, 'A "core.controller" listener returned a non response object.', $type);
            } catch (\Exception $e) {
                $retval = $event->getReturnValue();
            }
        } else {
            // call controller
            $retval = call_user_func_array($controller, $arguments);
        }

        // view
        $event = $this->dispatcher->filter(new Event($this, 'core.view', array('request_type' => $type, 'request' => $request)), $retval);

        return $this->filterResponse($event->getReturnValue(), $request, sprintf('The controller must return a response (instead of %s).', is_object($event->getReturnValue()) ? 'an object of class '.get_class($event->getReturnValue()) : is_array($event->getReturnValue()) ? 'an array' : str_replace("\n", '', var_export($event->getReturnValue(), true))), $type);
    }

    /**
     * Handles a request that need to be embedded.
     *
     * @param Request $request A Request instance
     * @param Boolean $raw Whether to catch exceptions or not
     *
     * @return string|false The Response content or false if there is a problem
     *
     * @throws \RuntimeException When an Exception occurs during processing
     *                           and couldn't be caught by event processing or $raw is true
     */
    protected function handleEmbedded(Request $request, $raw = false)
    {
        try {
            $response = $this->handleRaw($request, HttpKernelInterface::EMBEDDED_REQUEST);

            if (200 != $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $request->getUri(), $response->getStatusCode()));
            }

            return $response->getContent();
        } catch (\Exception $e) {
            if (true === $raw)
            {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Filters a response object.
     *
     * @param Response $response A Response instance
     * @param string   $message  A error message in case the response is not a Response object
     * @param integer  $type    The type of the request (one of HttpKernelInterface::MASTER_REQUEST, HttpKernelInterface::FORWARDED_REQUEST, or HttpKernelInterface::EMBEDDED_REQUEST)
     *
     * @return Response The filtered Response instance
     *
     * @throws \RuntimeException if the passed object is not a Response instance
     */
    protected function filterResponse($response, $request, $message, $type)
    {
        if (!$response instanceof Response) {
            throw new \RuntimeException($message);
        }

        $event = $this->dispatcher->filter(new Event($this, 'core.response', array('request_type' => $type, 'request' => $request)), $response);
        $response = $event->getReturnValue();

        if (!$response instanceof Response) {
            throw new \RuntimeException('A "core.response" listener returned a non response object.');
        }

        return $response;
    }
}
