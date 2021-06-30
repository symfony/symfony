<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Attribute;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * Attributes store.
 *
 * @author Drak <drak@zikula.org>
 */
interface AttributeBagInterface extends SessionBagInterface
{
    /**
     * Checks if an attribute is defined.
     *
     * @return bool true if the attribute is defined, false otherwise
     */
    public function has(string $name);

    /**
     * Returns an attribute.
     *
     * @return mixed
     */
    public function get(string $name, mixed $default = null);

    /**
     * Sets an attribute.
     */
    public function set(string $name, mixed $value);

    /**
     * Returns attributes.
     *
     * @return array
     */
    public function all();

    public function replace(array $attributes);

    /**
     * Removes an attribute.
     *
     * @return mixed The removed value or null when it does not exist
     */
    public function remove(string $name);
}
