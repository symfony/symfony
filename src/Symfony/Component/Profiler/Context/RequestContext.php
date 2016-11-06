<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 9/26/16
 * Time: 10:18 PM
 */

namespace Symfony\Component\Profiler\Context;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestContext implements ContextInterface
{
    protected $exception;
    protected $request;
    protected $response;

    public function __construct(Request $request, Response $response, $exception = null)
    {
        if (!is_null($exception) && !$exception instanceof \Exception && !$exception instanceof \Throwable) {
            throw new \InvalidArgumentException('$exception must be either null or an instance of \Exception or \Throwable');
        }

        $this->exception = $exception;
        $this->request = $request;
        $this->response = $response;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function getName()
    {
        return $this->request->getUri();
    }

    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return Types::REQUEST;
    }
}