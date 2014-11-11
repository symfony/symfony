<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Logout;

use Symfony\Component\Security\Http\Session\SessionRegistry;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handler for removing session information from the session registry.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistryLogoutHandler
{
    private $registry;

    /**
     * Constructor
     *
     * @param SessionRegistry $registry
     */
    public function __construct(SessionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Remove current session information from the session registry
     *
     * @param Request        $request
     * @param Response       $response
     * @param TokenInterface $token
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        if (null !== $session = $request->getSession()) {
            $this->registry->removeSessionInformation($session->getId());
        }
    }
}
