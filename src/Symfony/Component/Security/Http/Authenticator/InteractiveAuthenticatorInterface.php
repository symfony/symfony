<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

/**
 * This is an extension of the authenticator interface that may
 * be used by interactive authenticators.
 *
 * Interactive login requires explicit user action (e.g. a login
 * form or HTTP basic authentication). Implementing this interface
 * will dispatch the InteractiveLoginEvent upon successful login.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface InteractiveAuthenticatorInterface extends AuthenticatorInterface
{
    /**
     * Should return true to make this authenticator perform
     * an interactive login.
     */
    public function isInteractive(): bool;
}
