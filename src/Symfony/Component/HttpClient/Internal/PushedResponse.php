<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Symfony\Component\HttpClient\Response\CurlResponse;

/**
 * A pushed response with headers.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class PushedResponse
{
    /** @var CurlResponse */
    public $response;

    /** @var string[] */
    public $headers;

    public function __construct(CurlResponse $response, array $headers)
    {
        $this->response = $response;
        $this->headers = $headers;
    }
}
