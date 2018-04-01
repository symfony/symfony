<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Firewall;

use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Exception\BadCredentialsException;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * X509 authentication listener.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class X509AuthenticationListener extends AbstractPreAuthenticatedListener
{
    private $userKey;
    private $credentialKey;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, string $providerKey, string $userKey = 'SSL_CLIENT_S_DN_Email', string $credentialKey = 'SSL_CLIENT_S_DN', LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($tokenStorage, $authenticationManager, $providerKey, $logger, $dispatcher);

        $this->userKey = $userKey;
        $this->credentialKey = $credentialKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPreAuthenticatedData(Request $request)
    {
        $user = null;
        if ($request->server->has($this->userKey)) {
            $user = $request->server->get($this->userKey);
        } elseif ($request->server->has($this->credentialKey) && preg_match('#/emailAddress=(.+\@.+\..+)(/|$)#', $request->server->get($this->credentialKey), $matches)) {
            $user = $matches[1];
        }

        if (null === $user) {
            throw new BadCredentialsException(sprintf('SSL credentials not found: %s, %s', $this->userKey, $this->credentialKey));
        }

        return array($user, $request->server->get($this->credentialKey, ''));
    }
}
