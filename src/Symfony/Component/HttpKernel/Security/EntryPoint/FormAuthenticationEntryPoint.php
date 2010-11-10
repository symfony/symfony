<?php

namespace Symfony\Component\HttpKernel\Security\EntryPoint;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\SecurityContext;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FormAuthenticationEntryPoint starts an authentication via a login form.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FormAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    protected $loginPath;
    protected $useForward;

    /**
     * Constructor
     *
     * @param string  $loginPath  The path to the login form
     * @param Boolean $useForward Whether to forward or redirect to the login form
     */
    public function __construct($loginPath, $useForward = false)
    {
        $this->loginPath = $loginPath;
        $this->useForward = (Boolean) $useForward;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if ($this->useForward) {
            return $event->getSubject()->handle(Request::create($this->loginPath), HttpKernelInterface::SUB_REQUEST);
        }

        $response = new Response();
        $response->setRedirect(0 !== strpos($this->loginPath, 'http') ? $request->getUriForPath($this->loginPath) : $this->loginPath, 302);

        return $response;
    }
}
