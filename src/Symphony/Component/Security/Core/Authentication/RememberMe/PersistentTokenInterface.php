<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Authentication\RememberMe;

/**
 * Interface to be implemented by persistent token classes (such as
 * Doctrine entities representing a remember-me token).
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PersistentTokenInterface
{
    /**
     * Returns the class of the user.
     *
     * @return string
     */
    public function getClass();

    /**
     * Returns the username.
     *
     * @return string
     */
    public function getUsername();

    /**
     * Returns the series.
     *
     * @return string
     */
    public function getSeries();

    /**
     * Returns the token value.
     *
     * @return string
     */
    public function getTokenValue();

    /**
     * Returns the time the token was last used.
     *
     * @return \DateTime
     */
    public function getLastUsed();
}
