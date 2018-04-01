<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Authentication\Token\Storage;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The TokenStorageInterface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface TokenStorageInterface
{
    /**
     * Returns the current security token.
     *
     * @return TokenInterface|null A TokenInterface instance or null if no authentication information is available
     */
    public function getToken();

    /**
     * Sets the authentication token.
     *
     * @param TokenInterface $token A TokenInterface token, or null if no further authentication information should be stored
     */
    public function setToken(TokenInterface $token = null);
}
