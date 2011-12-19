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
 * UserComparatorInterface is used to compare two UserInterface
 * or AdvancedUserInterface objects, in a security and re-authentication context.
 *
 * @author Dariusz Górecki <darek.krk@gmail.com>
 */
interface UserComparatorInterface
{
    /**
     * The equality comparison should neither be done by referential equality
     * nor by comparing identities (i.e. getId() === getId()).
     *
     * However, you do not need to compare every attribute, but only those that
     * are relevant for assessing whether re-authentication is required.
     *
     * @param UserInterface $userA
     * @param UserInterface $userB
     *
     * @return Boolean
     */
    public function compareUsers(UserInterface $userA, UserInterface $userB);
}
