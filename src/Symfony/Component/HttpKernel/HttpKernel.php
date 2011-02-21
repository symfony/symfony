<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HttpKernel notifies events to convert a Request object to a Response one.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HttpKernel implements HttpKernelInterface
{
    protected $dispatcher;
    protected $resolver;

    /**
     * Constructor
     *
     * @param EventDispatcherInterface    $dispatcher An EventDispatcherInterface instance
     * @param ControllerResolverInterface $resolver A ControllerResolverInterface instance
     */
    public function __construct(EventDispatcherInterface $dispatcher, ControllerResolverInterface $resolver)
    {
        $this->dispatcher = $dispatcher;
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        try {
            $response = $this->handleRaw($request, $type);
        } catch (\Exception $e) {
            if (false === $catch) {
                throw $e;
            }

            // exception
            $event = new Event($this, 'core.exception', array('request_type' => $type, 'request' => $request, 'exception' => $e));
            $response = $this->dispatcher->notifyUntil($event);
            if (!$event->isProcessed()) {
                throw $e;
            }

            $response = $this->filterResponse($response, $request, 'A "core.exception" listener returned a non response object.', $type);
        }

        return $response;
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
        $response = $this->dispatcher->notifyUntil($event);
        if ($event->isProcessed()) {
            return $this->filterResponse($response, $request, 'A "core.request" listener returned a non response object.', $type);
        }

        // load controller
        if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". Maybe you forgot to add the matching route in your routing configuration?', $request->getPathInfo()));
        }

        $event = new Event($this, 'core.controller', array('request_type' => $type, 'request' => $request));
        $controller = $this->dispatcher->filter($event, $controller);

        // controller must be a callable
        if (!is_callable($controller)) {
            throw new \LogicException(sprintf('The controller must be a callable (%s given).', $this->varToString($controller)));
        }

        // controller arguments
        $arguments = $this->resolver->getArguments($request, $controller);

        // call controller
        $response = call_user_func_array($controller, $arguments);

        // view
        if (!$response instanceof Response) {
            $event = new Event($this, 'core.view', array('request_type' => $type, 'request' => $request, 'controller_value' => $response));
            $retval = $this->dispatcher->notifyUntil($event);
            if ($event->isProcessed()) {
                $response = $retval;
            }
        }

        return $this->filterResponse($response, $request, sprintf('The controller must return a response (%s given).', $this->varToString($response)), $type);
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

        $response = $this->dispatcher->filter(new Event($this, 'core.response', array('request_type' => $type, 'request' => $request)), $response);

        if (!$response instanceof Response) {
            throw new \RuntimeException('A "core.response" listener returned a non response object.');
        }

        return $response;
    }

    protected function varToString($var)
    {
        if (is_object($var)) {
            return sprintf('[object](%s)', get_class($var));
        }

        if (is_array($var)) {
            $a = array();
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => %s', $k, $this->varToString($v));
            }

            return sprintf("[array](%s)", implode(', ', $a));
        }

        if (is_resource($var)) {
            return '[resource]';
        }

        return str_replace("\n", '', var_export((string) $var, true));
    }
}
