<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * REMOTE_USER authentication listener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Douailin <maxime.douailin@gmail.com>
 */
class RemoteUserAuthenticationListener extends AbstractPreAuthenticatedListener
{
    private $userKey;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, string $providerKey, string $userKey = 'REMOTE_USER', LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($tokenStorage, $authenticationManager, $providerKey, $logger, $dispatcher);

        $this->userKey = $userKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPreAuthenticatedData(Request $request)
    {
        if (!$request->server->has($this->userKey)) {
            throw new BadCredentialsException(sprintf('User key was not found: %s', $this->userKey));
        }

        return array($request->server->get($this->userKey), null);
    }
}
