<?php

namespace Symfony\Component\HttpFoundation\SessionStorage;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SessionStorageInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface SessionStorageInterface
{
    /**
     * Starts the session.
     */
    function start();

    /**
     * Reads data from this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param  string $key  A unique key identifying your data
     *
     * @return mixed Data associated with the key
     *
     * @throws \RuntimeException If an error occurs while reading data from this storage
     */
    function read($key);

    /**
     * Removes data from this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param  string $key  A unique key identifying your data
     *
     * @return mixed Data associated with the key
     *
     * @throws \RuntimeException If an error occurs while removing data from this storage
     */
    function remove($key);

    /**
     * Writes data to this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param  string $key   A unique key identifying your data
     * @param  mixed  $data  Data associated with your key
     *
     * @throws \RuntimeException If an error occurs while writing to this storage
     */
    function write($key, $data);

    /**
     * Regenerates id that represents this storage.
     *
     * @param  boolean $destroy Destroy session when regenerating?
     *
     * @return boolean True if session regenerated, false if error
     *
     * @throws \RuntimeException If an error occurs while regenerating this storage
     */
    function regenerate($destroy = false);
}
