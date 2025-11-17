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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface UserAuthenticatorInterface
{
    /**
     * Convenience method to programmatically login a user and return a
     * Response *if any* for success.
     *
     * @param BadgeInterface[]     $badges     Optionally, pass some Passport badges to use for the manual login
     * @param array<string, mixed> $attributes Optionally, pass some Passport attributes to use for the manual login
     */
    public function authenticateUser(UserInterface $user, AuthenticatorInterface $authenticator, Request $request, array $badges = [], array $attributes = []): ?Response;
}
