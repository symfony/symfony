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
 * FilesystemSessionStorage simulates sessions for functional tests.
 *
 * This storage does not start the session (session_start())
 * as it is not "available" when running tests on the command line.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class FilesystemSessionStorage extends NativeSessionStorage
{
    private $path;
    private $data;
    private $started;

    /**
     * Constructor.
     */
    public function __construct($path, array $options = array())
    {
        $this->path = $path;
        $this->started = false;

        parent::__construct($options);
    }

    /**
     * Starts the session.
     *
     * @api
     */
    public function start()
    {
        if ($this->started) {
            return;
        }

        session_set_cookie_params(
            $this->options['lifetime'],
            $this->options['path'],
            $this->options['domain'],
            $this->options['secure'],
            $this->options['httponly']
        );

        if (!ini_get('session.use_cookies') && isset($this->options['id']) && $this->options['id'] && $this->options['id'] != session_id()) {
            session_id($this->options['id']);
        }

        if (!session_id()) {
            session_id(hash('md5', uniqid(mt_rand(), true)));
        }

        $file = $this->path.'/'.session_id().'.session';

        $this->data = file_exists($file) ? unserialize(file_get_contents($file)) : array();
        $this->started = true;
    }

    /**
     * Returns the session ID
     *
     * @return mixed  The session ID
     *
     * @throws \RuntimeException If the session was not started yet
     *
     * @api
     */
    public function getId()
    {
        if (!$this->started) {
            throw new \RuntimeException('The session must be started before reading its ID');
        }

        return session_id();
    }

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
     *
     * @api
     */
    public function read($key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

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
     *
     * @api
     */
    public function remove($key)
    {
        $retval = $this->data[$key];

        unset($this->data[$key]);

        return $retval;
    }

    /**
     * Writes data to this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param  string $key   A unique key identifying your data
     * @param  mixed  $data  Data associated with your key
     *
     * @throws \RuntimeException If an error occurs while writing to this storage
     *
     * @api
     */
    public function write($key, $data)
    {
        $this->data[$key] = $data;

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        file_put_contents($this->path.'/'.session_id().'.session', serialize($this->data));
    }

    /**
     * Regenerates id that represents this storage.
     *
     * @param  Boolean $destroy Destroy session when regenerating?
     *
     * @return Boolean True if session regenerated, false if error
     *
     * @throws \RuntimeException If an error occurs while regenerating this storage
     *
     * @api
     */
    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->data = array();
        }

        return true;
    }
}
