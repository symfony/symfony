<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Starts a fresh authentication for a user who is already authenticated.
 *
 * This is deliberately not an AuthenticationEntryPointInterface: that one answers
 * "who are you?" and an existing session already satisfies it, so reusing it would
 * send the user to a login page that lets them straight back through. Here the user
 * is known, and the only thing being asked for is a fresh proof that they still hold
 * their credentials, which is why the token is passed rather than an exception.
 *
 * An AuthenticationEntryPointInterface may implement this as well, in which case the
 * firewall uses it for both without any extra configuration.
 *
 * @see \Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY
 * @see \Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter::IS_AUTHENTICATED_VERY_RECENTLY
 */
interface ReAuthenticationEntryPointInterface
{
    public function startReAuthentication(Request $request, TokenInterface $token): Response;
}
