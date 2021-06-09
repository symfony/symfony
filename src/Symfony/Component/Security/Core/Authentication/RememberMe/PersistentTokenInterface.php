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
 * Doctrine entities representing a remember-me token).
 *
 * @method string getUserIdentifier() returns the identifier used to authenticate (e.g. their e-mailaddress or username)
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

    /**
     * @return string
     *
     * @deprecated since Symfony 5.3, use getUserIdentifier() instead
     */
    public function getUsername();
}
