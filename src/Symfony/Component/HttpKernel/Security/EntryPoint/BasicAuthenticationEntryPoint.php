<?php

namespace Symfony\Component\HttpKernel\Security\EntryPoint;

use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BasicAuthenticationEntryPoint starts an HTTP Basic authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BasicAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    protected $realmName;

    public function __construct($realmName)
    {
        $this->realmName = $realmName;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $response = new Response();
        $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', $this->realmName));
        $response->setStatusCode(401, $authException->getMessage());

        return $response;
    }
}
