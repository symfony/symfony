<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows to filter a Response object
 *
 * You can call getResponse() to retrieve the current response. With
 * setResponse() you can set a new response that will be returned to the
 * browser.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FilterResponseEvent extends KernelEvent
{
    /**
     * The current response object
     * @var Symfony\Component\HttpFoundation\Response
     */
    private $response;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, Response $response)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setResponse($response);
    }

    /**
     * Returns the current response object
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets a new response object
     *
     * @param Symfony\Component\HttpFoundation\Response $response
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
    }
}