<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\LoginLink;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A class that is able to create and handle "magic" login links.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface LoginLinkHandlerInterface
{
    /**
     * Generate a link that can be used to authenticate as the given user.
     *
     * @param int|null $lifetime When not null, the argument overrides any default lifetime previously set
     */
    public function createLoginLink(UserInterface $user, Request $request = null, int $lifetime = null): LoginLinkDetails;

    /**
     * Validates if this request contains a login link and returns the associated User.
     *
     * Throw InvalidLoginLinkExceptionInterface if the link is invalid.
     */
    public function consumeLoginLink(Request $request): UserInterface;
}
