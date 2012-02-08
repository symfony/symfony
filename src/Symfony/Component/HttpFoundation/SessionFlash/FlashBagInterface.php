<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionFlash;

use Symfony\Component\HttpFoundation\SessionBagInterface;

/**
 * FlashBagInterface.
 *
 * @author Drak <drak@zikula.org>
 */
interface FlashBagInterface extends SessionBagInterface
{
    const INFO = 'info';
    const NOTICE = 'notice';
    const WARNING = 'warning';
    const ERROR = 'error';

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
     * @param string $type Message category type.
     *
     * @return string
     */
    function get($type);

    /**
     * Gets all flash messages.
     *
     * @return array
     */
    function all();

    /**
     * Pops and clears flash from the stack.
     *
     * @param string $type
     *
     * @return string
     */
    function pop($type);

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
