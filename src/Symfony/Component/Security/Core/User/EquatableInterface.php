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

/**
 * EquatableInterface used to test if two objects are equal in security
 * and re-authentication context.
 *
 * Implementing it replaces the built-in password, roles and identifier checks.
 *
 * @see UserChangeAwareInterface to add checks to the built-in ones instead of replacing them
 *
 * @author Dariusz Górecki <darek.krk@gmail.com>
 */
interface EquatableInterface
{
    /**
     * The equality comparison should neither be done by referential equality
     * nor by comparing identities (i.e. getId() === getId()).
     *
     * However, you do not need to compare every attribute, but only those that
     * are relevant for assessing whether re-authentication is required.
     */
    public function isEqualTo(UserInterface $user): bool;
}
