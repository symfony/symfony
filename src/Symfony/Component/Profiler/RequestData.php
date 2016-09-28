<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 9/26/16
 * Time: 10:18 PM
 */

namespace Symfony\Component\Profiler;

use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestData implements DataInterface
{
    protected $exception;
    protected $request;
    protected $response;

    public function __construct(Request $request, Response $response, $exception = null)
    {
        if (!is_null($exception) && !$exception instanceof \Exception && !$exception instanceof \Throwable) {
            throw new \InvalidArgumentException('$exception must be either null or an exception');
        }

        $this->exception = $exception;
        $this->request = $request;
        $this->response = $response;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function getUri()
    {
        return $this->request->getUri();
    }

    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    public function getMethod()
    {
        return $this->request->getMethod();
    }

    public function getClientIp()
    {
        try {
            return $this->request->getClientIp();
        } catch (ConflictingHeadersException $e) {
            return 'Unknown';
        }
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
}