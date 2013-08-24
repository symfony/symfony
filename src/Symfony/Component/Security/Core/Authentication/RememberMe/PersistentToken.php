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
 * This class is only used by PersistentTokenRememberMeServices internally.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
final class PersistentToken implements PersistentTokenInterface
{
    private $class;
    private $username;
    private $series;
    private $tokenValue;
    private $lastUsed;

    /**
     * Constructor
     *
     * @param string    $class
     * @param string    $username
     * @param string    $series
     * @param string    $tokenValue
     * @param \DateTime $lastUsed
     *
     * @throws \InvalidArgumentException
     *
     * @since v2.0.0
     */
    public function __construct($class, $username, $series, $tokenValue, \DateTime $lastUsed)
    {
        if (empty($class)) {
            throw new \InvalidArgumentException('$class must not be empty.');
        }
        if (empty($username)) {
            throw new \InvalidArgumentException('$username must not be empty.');
        }
        if (empty($series)) {
            throw new \InvalidArgumentException('$series must not be empty.');
        }
        if (empty($tokenValue)) {
            throw new \InvalidArgumentException('$tokenValue must not be empty.');
        }

        $this->class = $class;
        $this->username = $username;
        $this->series = $series;
        $this->tokenValue = $tokenValue;
        $this->lastUsed = $lastUsed;
    }

    /**
     * Returns the class of the user
     *
     * @return string
     *
     * @since v2.0.0
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Returns the username
     *
     * @return string
     *
     * @since v2.0.0
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Returns the series
     *
     * @return string
     *
     * @since v2.0.0
     */
    public function getSeries()
    {
        return $this->series;
    }

    /**
     * Returns the token value
     *
     * @return string
     *
     * @since v2.0.0
     */
    public function getTokenValue()
    {
        return $this->tokenValue;
    }

    /**
     * Returns the time the token was last used
     *
     * @return DateTime
     *
     * @since v2.0.0
     */
    public function getLastUsed()
    {
        return $this->lastUsed;
    }
}
