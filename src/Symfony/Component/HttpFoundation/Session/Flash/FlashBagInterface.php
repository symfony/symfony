<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Flash;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * FlashBagInterface.
 *
 * @author Drak <drak@zikula.org>
 */
interface FlashBagInterface extends SessionBagInterface
{
    /**
     * Registers a message for a given type.
     *
     * @param string $type
     * @param string $message
     */
    function set($type, $message);

    /**
     * Gets flash message for a given type.
     *
     * @param string $type    Message category type.
     * @param string $default Default value if $type doee not exist.
     *
     * @return string
     */
    function peek($type, $default = null);

    /**
     * Gets all flash messages.
     *
     * @return array
     */
    function peekAll();

    /**
     * Pops and clears flash from the stack.
     *
     * @param string $type
     * @param string $default Default value if $type doee not exist.
     *
     * @return string
     */
    function pop($type, $default = null);

    /**
     * Pops and clears flashes from the stack.
     *
     * @return array
     */
    function popAll();

    /**
     * Sets all flash messages.
     */
    function setAll(array $messages);

    /**
     * Has flash messages for a given type?
     *
     * @param string $type
     *
     * @return boolean
     */
    function has($type);

    /**
     * Returns a list of all defined types.
     *
     * @return array
     */
    function keys();
}
