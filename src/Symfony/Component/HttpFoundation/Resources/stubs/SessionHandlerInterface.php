<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * SessionHandlerInterface for PHP < 5.4.
 *
 * The order in which these methods are invoked by PHP are:
 * 1. open [session_start]
 * 2. read
 * 3. gc [optional depending on probability settings: gc_probability / gc_divisor]
 * 4. destroy [optional when session_regenerate_id(true) is used]
 * 5. write [session_write_close] or destroy [session_destroy]
 * 6. close
 *
 * Extensive documentation can be found at php.net, see links:
 *
 * @see http://php.net/sessionhandlerinterface
 * @see http://php.net/session.customhandler
 * @see http://php.net/session-set-save-handler
 *
 * @author Drak <drak@zikula.org>
 * @author Tobias Schultze <http://tobion.de>
 */
interface SessionHandlerInterface
{
    /**
     * Re-initializes existing session, or creates a new one.
     *
     * @see http://php.net/sessionhandlerinterface.open
     *
     * @param string $savePath    Save path
     * @param string $sessionName Session name, see http://php.net/function.session-name.php
     *
     * @return bool true on success, false on failure
     */
    public function open($savePath, $sessionName);

    /**
     * Closes the current session.
     *
     * @see http://php.net/sessionhandlerinterface.close
     *
     * @return bool true on success, false on failure
     */
    public function close();

    /**
     * Reads the session data.
     *
     * @see http://php.net/sessionhandlerinterface.read
     *
     * @param string $sessionId Session ID, see http://php.net/function.session-id
     *
     * @return string Same session data as passed in write() or empty string when non-existent or on failure
     */
    public function read($sessionId);

    /**
     * Writes the session data to the storage.
     *
     * Care, the session ID passed to write() can be different from the one previously
     * received in read() when the session ID changed due to session_regenerate_id().
     *
     * @see http://php.net/sessionhandlerinterface.write
     *
     * @param string $sessionId Session ID , see http://php.net/function.session-id
     * @param string $data      Serialized session data to save
     *
     * @return bool true on success, false on failure
     */
    public function write($sessionId, $data);

    /**
     * Destroys a session.
     *
     * @see http://php.net/sessionhandlerinterface.destroy
     *
     * @param string $sessionId Session ID, see http://php.net/function.session-id
     *
     * @return bool true on success, false on failure
     */
    public function destroy($sessionId);

    /**
     * Cleans up expired sessions (garbage collection).
     *
     * @see http://php.net/sessionhandlerinterface.gc
     *
     * @param string|int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed
     *
     * @return bool true on success, false on failure
     */
    public function gc($maxlifetime);
}
