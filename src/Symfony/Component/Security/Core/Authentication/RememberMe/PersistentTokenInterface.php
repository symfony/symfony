<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

/**
 * Interface to be implemented by persistent token classes (such as
 * Doctrine entities representing a remember-me token)
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PersistentTokenInterface
{
    /**
     * Returns the class of the user
     * @return string
     *
     * @since v2.1.0
     */
    public function getClass();

    /**
     * Returns the username
     * @return string
     *
     * @since v2.1.0
     */
    public function getUsername();

    /**
     * Returns the series
     * @return string
     *
     * @since v2.1.0
     */
    public function getSeries();

    /**
     * Returns the token value
     * @return string
     *
     * @since v2.1.0
     */
    public function getTokenValue();

    /**
     * Returns the last time the cookie was used
     * @return \DateTime
     *
     * @since v2.1.0
     */
    public function getLastUsed();
}
