<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates PSR HTTP Request and Response instances from Symfony ones.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface HttpMessageFactoryInterface
{
    /**
     * Creates a PSR-7 Request instance from a Symfony one.
     *
     * @return ServerRequestInterface
     */
    public function createRequest(Request $symfonyRequest);

    /**
     * Creates a PSR-7 Response instance from a Symfony one.
     *
     * @return ResponseInterface
     */
    public function createResponse(Response $symfonyResponse);
}
