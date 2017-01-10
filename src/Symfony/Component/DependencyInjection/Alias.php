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

class Alias
{
    private $id;
    private $public;

    /**
     * @param string $id     Alias identifier
     * @param bool   $public If this alias is public
     */
    public function __construct($id, $public = true)
    {
        if (!is_string($id)) {
            $type = is_object($id) ? get_class($id) : gettype($id);
            $id = (string) $id;
            @trigger_error(sprintf('Non-string identifiers are deprecated since Symfony 3.3 and won\'t be supported in 4.0 for Alias to "%s" ("%s" given.) Cast it to string beforehand.', $id, $type), E_USER_DEPRECATED);
        }
        $this->id = $id;
        $this->public = $public;
    }

    /**
     * Checks if this DI Alias should be public or not.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets if this Alias is public.
     *
     * @param bool $boolean If this Alias should be public
     */
    public function setPublic($boolean)
    {
        $this->public = (bool) $boolean;
    }

    /**
     * Returns the Id of this alias.
     *
     * @return string The alias id
     */
    public function __toString()
    {
        return $this->id;
    }
}
