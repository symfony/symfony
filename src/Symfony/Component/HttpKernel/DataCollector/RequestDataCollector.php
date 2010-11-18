<?php

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RequestDataCollector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestDataCollector extends DataCollector
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'format'             => $request->getRequestFormat(),
            'content_type'       => $response->headers->get('Content-Type') ? $response->headers->get('Content-Type') : 'text/html',
            'status_code'        => $response->getStatusCode(),
            'request_query'      => $request->query->all(),
            'request_request'    => $request->request->all(),
            'request_headers'    => $request->headers->all(),
            'request_server'     => $request->server->all(),
            'request_cookies'    => $request->cookies->all(),
            'request_attributes' => $request->attributes->all(),
            'response_headers'   => $response->headers->all(),
            'session_attributes' => $request->hasSession() ? $request->getSession()->getAttributes() : array(),
        );
    }

    public function getRequestRequest()
    {
        return new ParameterBag($this->data['request_request']);
    }

    public function getRequestQuery()
    {
        return new ParameterBag($this->data['request_query']);
    }

    public function getRequestHeaders()
    {
        return new HeaderBag($this->data['request_headers']);
    }

    public function getRequestServer()
    {
        return new ParameterBag($this->data['request_server']);
    }

    public function getRequestCookies()
    {
        return new ParameterBag($this->data['request_cookies']);
    }

    public function getRequestAttributes()
    {
        return new ParameterBag($this->data['request_attributes']);
    }

    public function getResponseHeaders()
    {
        return new ResponseHeaderBag($this->data['response_headers']);
    }

    public function getSessionAttributes()
    {
        return $this->data['session_attributes'];
    }

    public function getContentType()
    {
        return $this->data['content_type'];
    }

    public function getStatusCode()
    {
        return $this->data['status_code'];
    }

    public function getFormat()
    {
        return $this->data['format'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'request';
    }
}
