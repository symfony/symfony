<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * Thrown when access was denied because the user did not authenticate recently enough.
 *
 * The user is authenticated, so this is not a failure to identify them: it asks them to
 * prove possession of their credentials again before a sensitive action. An entry point
 * receiving this should start a fresh authentication rather than an ordinary login, which
 * an existing session would otherwise satisfy without the user typing anything.
 *
 * @see \Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY
 */
class ReAuthenticationRequiredException extends InsufficientAuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Re-authentication is required to access this resource.';
    }
}
