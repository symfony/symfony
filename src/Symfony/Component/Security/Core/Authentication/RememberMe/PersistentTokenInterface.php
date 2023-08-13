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
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PersistentTokenInterface
{
    /**
     * Returns the class of the user.
     */
    public function getClass(): string;

    /**
     * Returns the series.
     */
    public function getSeries(): string;

    /**
     * Returns the token value.
     */
    public function getTokenValue(): string;

    /**
     * Returns the time the token was last used.
     *
     * Each call SHOULD return a new distinct DateTime instance.
     */
    public function getLastUsed(): \DateTime;

    /**
     * Returns the identifier used to authenticate (e.g. their email address or username).
     */
    public function getUserIdentifier(): string;
}
