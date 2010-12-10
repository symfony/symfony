<?php

namespace Symfony\Component\HttpKernel;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HttpKernel implements HttpKernelInterface
{
    protected $dispatcher;
    protected $resolver;
    protected $request;

    /**
     * Constructor
     *
     * @param EventDispatcher             $dispatcher An event dispatcher instance
     * @param ControllerResolverInterface $resolver A ControllerResolverInterface instance
     */
    public function __construct(EventDispatcher $dispatcher, ControllerResolverInterface $resolver)
    {
        $this->dispatcher = $dispatcher;
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        // set the current request, stash the previous one
        $previousRequest = $this->request;
        $this->request = $request;

        try {
            $response = $this->handleRaw($request, $type);
        } catch (\Exception $e) {
            if (false === $catch) {
                $this->request = $previousRequest;

                throw $e;
            }

            // exception
            $event = new Event($this, 'core.exception', array('request_type' => $type, 'request' => $request, 'exception' => $e));
            $this->dispatcher->notifyUntil($event);
            if (!$event->isProcessed()) {
                $this->request = $previousRequest;

                throw $e;
            }

            $response = $this->filterResponse($event->getReturnValue(), $request, 'A "core.exception" listener returned a non response object.', $type);
        }

        // restore the previous request
        $this->request = $previousRequest;

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param Request $request A Request instance
     * @param integer $type The type of the request (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     *
     * @return Response A Response instance
     *
     * @throws \LogicException If one of the listener does not behave as expected
     * @throws NotFoundHttpException When controller cannot be found
     */
    protected function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // request
        $event = new Event($this, 'core.request', array('request_type' => $type, 'request' => $request));
        $this->dispatcher->notifyUntil($event);
        if ($event->isProcessed()) {
            return $this->filterResponse($event->getReturnValue(), $request, 'A "core.request" listener returned a non response object.', $type);
        }

        // load controller
        if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException('Unable to find the controller.');
        }

        $event = new Event($this, 'core.controller', array('request_type' => $type, 'request' => $request));
        $this->dispatcher->filter($event, $controller);
        $controller = $event->getReturnValue();

        // controller must be a callable
        if (!is_callable($controller)) {
            throw new \LogicException(sprintf('The controller must be a callable (%s).', var_export($controller, true)));
        }

        // controller arguments
        $arguments = $this->resolver->getArguments($request, $controller);

        // call controller
        $retval = call_user_func_array($controller, $arguments);

        // view
        $event = new Event($this, 'core.view', array('request_type' => $type, 'request' => $request));
        $this->dispatcher->filter($event, $retval);

        return $this->filterResponse($event->getReturnValue(), $request, sprintf('The controller must return a response (instead of %s).', is_object($event->getReturnValue()) ? 'an object of class '.get_class($event->getReturnValue()) : is_array($event->getReturnValue()) ? 'an array' : str_replace("\n", '', var_export($event->getReturnValue(), true))), $type);
    }

    /**
     * Filters a response object.
     *
     * @param Response $response A Response instance
     * @param string   $message A error message in case the response is not a Response object
     * @param integer  $type The type of the request (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
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
