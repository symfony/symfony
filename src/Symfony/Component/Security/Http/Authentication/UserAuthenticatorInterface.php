<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in Symfony 5.1
 */
interface UserAuthenticatorInterface
{
    /**
     * Convenience method to programmatically login a user and return a
     * Response *if any* for success.
     */
    public function authenticateUser(UserInterface $user, AuthenticatorInterface $authenticator, Request $request): ?Response;
}
