<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Event\ControllerEventArgs;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpKernel\Event\ExceptionEventArgs;
use Symfony\Component\HttpKernel\Event\ViewEventArgs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\EventManager;

/**
 * HttpKernel notifies events to convert a Request object to a Response one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpKernel implements HttpKernelInterface
{
    protected $evm;
    protected $resolver;

    /**
     * Constructor
     *
     * @param EventManager $evm An EventManager instance
     * @param ControllerResolverInterface $resolver A ControllerResolverInterface instance
     */
    public function __construct(EventManager $evm, ControllerResolverInterface $resolver)
    {
        $this->evm = $evm;
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
            $eventArgs = new ExceptionEventArgs($this, $e, $request, $type);
            $this->evm->dispatchEvent(Events::onCoreException, $eventArgs);

            if (!$eventArgs->isHandled()) {
                throw $e;
            }

            $response = $this->filterResponse($eventArgs->getResponse(), $request, $type);
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
        $eventArgs = new RequestEventArgs($this, $request, $type);
        $this->evm->dispatchEvent(Events::onCoreRequest, $eventArgs);

        if ($eventArgs->hasResponse()) {
            return $this->filterResponse($eventArgs->getResponse(), $request, $type);
        }

        // load controller
        if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". Maybe you forgot to add the matching route in your routing configuration?', $request->getPathInfo()));
        }

        $eventArgs = new ControllerEventArgs($this, $controller, $request, $type);
        $this->evm->dispatchEvent(Events::filterCoreController, $eventArgs);
        $controller = $eventArgs->getController();

        // controller arguments
        $arguments = $this->resolver->getArguments($request, $controller);

        // call controller
        $response = call_user_func_array($controller, $arguments);

        // view
        if (!$response instanceof Response) {
            $eventArgs = new ViewEventArgs($this, $response, $request, $type);
            $this->dispatchEvent(Events::onCoreView, $eventArgs);

            if ($eventArgs->hasResponse()) {
                $response = $eventArgs->getResponse();
            }

            if (!$response instanceof Response) {
                throw new \LogicException(sprintf('The controller must return a response (%s given).', $this->varToString($response)));
            }
        }

        return $this->filterResponse($response, $request, $type);
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
    protected function filterResponse(Response $response, Request $request, $type)
    {
        $eventArgs = new RequestEventArgs($this, $request, $type, $response);

        $this->evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        return $event->getResponse();
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
