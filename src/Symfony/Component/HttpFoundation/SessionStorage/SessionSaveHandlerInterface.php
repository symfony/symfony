<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

/**
 * Session Savehandler Interface.
 * 
 * This interface is for implementing methods required for the 
 * session_set_save_handler() function.
 * 
 * @see http://php.net/session_set_save_handler
 * 
 * These are methods called by PHP when the session is started 
 * and closed and for various house-keeping tasks required
 * by session management.
 * 
 * PHP requires session save handlers.  There are some defaults set automatically
 * when PHP starts, but these can be overriden using this command if you need anything
 * other than PHP's default handling.
 * 
 * When the session starts, PHP will call the sessionRead() handler which should return a string 
 * extactly as stored (which will have been encoded by PHP using a special session serializer 
 * session_decode() which is different to the serialize() function.  PHP will then populate these 
 * into $_SESSION.
 * 
 * When PHP shuts down, the sessionWrite() handler is called and will pass the $_SESSION contents
 * to be stored.  Again PHP will automatically serialize these itself using session_encode()
 * 
 * When a session is specifically destroyed, PHP will call the sessionDestroy() handler with the
 * session ID.  This happens when the session is regenerated for example and th handler 
 * MUST delete the session by ID from the persistent storage immediately.
 * 
 * PHP will call sessionGc() from time to time to expire any session records according to the
 * set max lifetime of a session.  This routine should delete all records from persistent
 * storage which were last accessed longer than the $lifetime.
 * 
 * PHP sessionOpen() and sessionClose() are pretty much redundant and can return true.
 * 
 * @author Drak <drak@zikula.org>
 */
interface SessionSaveHandlerInterface
{
    /**
     * Open session.
     * 
     * This method is for internal use by PHP and must not be called manually.
     *
     * @param string $savePath    Save path.
     * @param string $sessionName Session Name.
     *
     * @return boolean
     */
    public function sessionOpen($savePath, $sessionName);

    /**
     * Close session.
     * 
     * This method is for internal use by PHP and must not be called manually.
     *
     * @return boolean
     */
    public function sessionClose();

    /**
     * Read session.
     * 
     * This method is for internal use by PHP and must not be called manually.
     * 
     * This method is called by PHP itself when the session is started.
     * This method should retrieve the session data from storage by the 
     * ID provided by PHP. Return the string directly as is from storage.
     * If the record was not found you must return an empty string.
     * 
     * The returned data will be automatically unserialized by PHP using a 
     * special unserializer method session_decode() and the result will be used
     * to populate the $_SESSION superglobal.  This is done automatically and 
     * is not configurable.
     *
     * @param string $sessionId Session ID.
     * 
     * @throws \RuntimeException On fatal error but not "record not found".
     *
     * @return string String as stored in persistent storage or empty string in all other cases.
     */
    public function sessionRead($sessionId);

    /**
     * Commit session to storage.
     * 
     * This method is for internal use by PHP and must not be called manually.
     * 
     * PHP will call this method when the session is closed.  It sends
     * the session ID and the contents of $_SESSION to be saved in a lightweight 
     * serialized format (which PHP does automatically using session_encode()
     * which should be stored exactly as is given in $data. 
     * 
     * Note this method is normally called by PHP after the output buffers
     * have been closed.
     *
     * @param string $sessionId Session ID.
     * @param string $data      Session serialized data to save.
     *
     * @throws \RuntimeException On fatal error.
     * 
     * @return boolean
     */
    public function sessionWrite($sessionId, $data);

    /**
     * Destroys this session.
     * 
     * This method is for internal use by PHP and must not be called manually.
     * 
     * PHP will call this method when the session data associated 
     * with the session ID provided needs to be immediately
     * deleted from the permanent storage.
     *
     * @param string $sessionId Session ID.
     * 
     * @throws \RuntimeException On fatal error.
     *
     * @return boolean
     */
    public function sessionDestroy($sessionId);

    /**
     * Garbage collection for storage.
     * 
     * This method is for internal use by PHP and must not be called manually.
     * 
     * This method is called by PHP periodically and passes the maximum
     * time a session can exist for before being deleted from permanent storage.  
     * 
     * @param integer $lifetime Max lifetime in seconds to keep sessions stored.
     * 
     * @throws \RuntimeException On fatal error.
     *
     * @return boolean
     */
    public function sessionGc($lifetime);
}
