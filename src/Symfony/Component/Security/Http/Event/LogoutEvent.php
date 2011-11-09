<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;

class LogoutEvent extends Event
{
    private $request;
    private $response;
    private $token;

    public function __construct(Request $request, Response $response, TokenInterface $token)
    {
        $this->request = $request;
        $this->response = $response;
        $this->token = $token;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getAuthenticationToken()
    {
        return $this->token;
    }
}
