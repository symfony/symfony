<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\RememberMe;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Handles creating and validating remember-me cookies.
 *
 * If you want to add a custom implementation, you want to extend from
 * {@see AbstractRememberMeHandler} instead.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface RememberMeHandlerInterface
{
    /**
     * Creates a remember-me cookie.
     *
     * The actual cookie should be set as an attribute on the main request,
     * which is transformed into a response cookie by {@see ResponseListener}.
     */
    public function createRememberMeCookie(UserInterface $user): void;

    /**
     * Validates the remember-me cookie and returns the associated User.
     *
     * Every cookie should only be used once. This means that this method should also:
     * - Create a new remember-me cookie to be sent with the response (using the
     *   {@see ResponseListener::COOKIE_ATTR_NAME} request attribute);
     * - If you store the token somewhere else (e.g. in a database), invalidate the
     *   stored token.
     *
     * @throws AuthenticationException
     */
    public function consumeRememberMeCookie(RememberMeDetails $rememberMeDetails): UserInterface;

    /**
     * Clears the remember-me cookie.
     *
     * This should set a cookie with a `null` value on the request attribute.
     */
    public function clearRememberMeCookie(): void;
}
