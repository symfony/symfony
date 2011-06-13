<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

/**
 * SessionStoragePersistenceInterface implements the required methods by php.net/session_set_save_handler
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
interface SessionStoragePersistenceInterface
{
    /**
     * Called upon opening a new session (callback set by session_set_save_handler)
     *
     * @param string $savePath The path to save to
     * @param string $sessionName The name of the session
     * @return void
     */
    function open($savePath, $sessionName);

    /**
     * Called upon closing a session (callback set by session_set_save_handler)
     *
     * @return void
     */
    function close();

    /**
     * Called upon reading a session (callback set by session_set_save_handler)
     *
     * @param string $id
     * @return void
     */
    function read($id);

    /**
     * Called upon writing a session (callback set by session_set_save_handler)
     *
     * @param string $id
     * @param string $data
     * @return void
     */
    function write($id, $data);

    /**
     * Called upon destroying a session (callback set by session_set_save_handler)
     *
     * @param  string $id
     * @return void
     */
    function destroy($id);

    /**
     * Called upon garbage collection (callback set by session_set_save_handler)
     *
     * @param int $maxlifetime
     * @return void
     */
    function gc($maxlifetime);
}
