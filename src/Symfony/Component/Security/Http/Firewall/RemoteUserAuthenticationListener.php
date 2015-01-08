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

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * REMOTE_USER authentication listener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Douailin <maxime.douailin@gmail.com>
 */
class RemoteUserAuthenticationListener extends AbstractPreAuthenticatedListener
{
    private $userKey;

    /**
     * @param SecurityContextInterface|TokenStorageInterface
     *
     * Passing a SecurityContextInterface as a first argument was deprecated in 2.7 and will be removed in 3.0
     */
    public function __construct($tokenStorage, AuthenticationManagerInterface $authenticationManager, $providerKey, $userKey = 'REMOTE_USER', LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
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
