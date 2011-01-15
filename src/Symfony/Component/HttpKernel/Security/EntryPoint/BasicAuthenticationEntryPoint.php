<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Security\EntryPoint;

use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
