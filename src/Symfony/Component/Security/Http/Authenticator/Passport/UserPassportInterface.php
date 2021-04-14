<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents a passport for a Security User.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface UserPassportInterface extends PassportInterface
{
    /**
     * @throws AuthenticationException when the user cannot be found
     */
    public function getUser(): UserInterface;
}
