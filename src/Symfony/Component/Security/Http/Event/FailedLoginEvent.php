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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class FailedLoginEvent extends Event
{
    private $request;

    private $authenticationException;

    public function __construct(Request $request, AuthenticationException $authenticationException)
    {
        $this->request = $request;
        $this->authenticationException = $authenticationException;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getAuthenticationException()
    {
        return $this->authenticationException;
    }
}

