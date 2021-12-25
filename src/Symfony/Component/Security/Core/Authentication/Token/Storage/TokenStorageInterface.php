<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token\Storage;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The TokenStorageInterface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface TokenStorageInterface
{
    /**
     * Returns the current security token.
     */
    public function getToken(): ?TokenInterface;

    /**
     * Sets the authentication token.
     *
     * @param TokenInterface|null $token A TokenInterface token, or null if no further authentication information should be stored
     */
    public function setToken(TokenInterface $token = null);
}
