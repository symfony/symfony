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
 * Creates Symfony Request and Response instances from PSR-7 ones.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface HttpFoundationFactoryInterface
{
    /**
     * Creates a Symfony Request instance from a PSR-7 one.
     */
    public function createRequest(ServerRequestInterface $psrRequest, bool $streamed = false): Request;

    /**
     * Creates a Symfony Response instance from a PSR-7 one.
     */
    public function createResponse(ResponseInterface $psrResponse, bool $streamed = false): Response;
}
