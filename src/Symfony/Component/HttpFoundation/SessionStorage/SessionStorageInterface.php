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
 * SessionStorageInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface SessionStorageInterface
{
    /**
     * Starts the session.
     *
     * @api
     */
    public function start();

    /**
     * Returns the session ID
     *
     * @return mixed  The session ID
     *
     * @throws \RuntimeException If the session was not started yet
     *
     * @api
     */
    public function getId();

    /**
     * Reads data from this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param string $key A unique key identifying your data
     *
     * @return mixed Data associated with the key
     *
     * @throws \RuntimeException If an error occurs while reading data from this storage
     *
     * @api
     */
    public function read($key);

    /**
     * Removes data from this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param string $key A unique key identifying your data
     *
     * @return mixed Data associated with the key
     *
     * @throws \RuntimeException If an error occurs while removing data from this storage
     *
     * @api
     */
    public function remove($key);

    /**
     * Writes data to this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param string $key  A unique key identifying your data
     * @param mixed  $data Data associated with your key
     *
     * @throws \RuntimeException If an error occurs while writing to this storage
     *
     * @api
     */
    public function write($key, $data);

    /**
     * Regenerates id that represents this storage.
     *
     * @param Boolean $destroy Destroy session when regenerating?
     *
     * @return Boolean True if session regenerated, false if error
     *
     * @throws \RuntimeException If an error occurs while regenerating this storage
     *
     * @api
     */
    public function regenerate($destroy = false);
}
