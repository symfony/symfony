<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * A signaling exception that wraps a lazily computed response.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LazyResponseException extends \Exception implements ExceptionInterface
{
    private $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
