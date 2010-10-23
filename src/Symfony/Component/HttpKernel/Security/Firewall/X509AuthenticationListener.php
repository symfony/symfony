<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Exception\BadCredentialsException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * X509 authentication listener.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class X509AuthenticationListener extends PreAuthenticatedListener
{
    protected $userKey;
    protected $credentialKey;

    public function __construct(SecurityContext $securityContext, AuthenticationManagerInterface $authenticationManager, $userKey = 'SSL_CLIENT_S_DN_Email', $credentialKey = 'SSL_CLIENT_S_DN', LoggerInterface $logger = null)
    {
        parent::__construct($securityContext, $authenticationManager, $logger);

        $this->userKey = $userKey;
        $this->credentialKey = $credentialKey;
    }

    protected function getPreAuthenticatedData(Request $request)
    {
        if (!$request->server->has($this->userKey)) {
            throw new BadCredentialsException(sprintf('SSL key was not found: %s', $this->userKey));
        }

        return array($request->server->get($this->userKey), $request->server->get($this->credentialKey, ''));
    }
}
