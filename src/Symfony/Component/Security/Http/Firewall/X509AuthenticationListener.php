<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * X509 authentication listener.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class X509AuthenticationListener extends AbstractPreAuthenticatedListener
{
    protected $userKey;
    protected $credentialKey;

    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, $providerKey, $userKey = 'SSL_CLIENT_S_DN_Email', $credentialKey = 'SSL_CLIENT_S_DN', LoggerInterface $logger = null)
    {
        parent::__construct($securityContext, $authenticationManager, $providerKey, $logger);

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
