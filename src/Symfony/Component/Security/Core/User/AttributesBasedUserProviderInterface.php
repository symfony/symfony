<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Overrides UserProviderInterface to add an "attributes" argument on loadUserByIdentifier.
 * This is particularly useful with self-contained access tokens.
 *
 * @template-covariant TUser of UserInterface
 *
 * @template-extends UserProviderInterface<TUser>
 */
interface AttributesBasedUserProviderInterface extends UserProviderInterface
{
    /**
     * Loads the user for the given user identifier (e.g. username or email) and attributes.
     *
     * This method must throw UserNotFoundException if the user is not found.
     *
     * @return TUser
     *
     * @throws UserNotFoundException
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface;
}
