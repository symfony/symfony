<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\User;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @see \Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface
 */
interface AttributesBasedUserLoaderInterface extends UserLoaderInterface
{
    /**
     * Loads the user for the given user identifier (e.g. username or email) and attributes.
     *
     * This method must return null if the user is not found.
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): ?UserInterface;
}
