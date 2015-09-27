<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * @api
 */
class Alias
{
    private $id;
    private $public;

    /**
     * Constructor.
     *
     * @param string $id     Alias identifier
     * @param bool   $public If this alias is public
     *
     * @api
     */
    public function __construct($id, $public = true)
    {
        $this->id = strtolower($id);
        $this->public = $public;
    }

    /**
     * Checks if this DI Alias should be public or not.
     *
     * @return bool
     *
     * @api
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets if this Alias is public.
     *
     * @param bool $boolean If this Alias should be public
     *
     * @api
     */
    public function setPublic($boolean)
    {
        $this->public = (bool) $boolean;
    }

    /**
     * Returns the Id of this alias.
     *
     * @return string The alias id
     *
     * @api
     */
    public function __toString()
    {
        return $this->id;
    }
}
