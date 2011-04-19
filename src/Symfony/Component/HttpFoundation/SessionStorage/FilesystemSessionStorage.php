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
 * FilesystemSessionStorage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FilesystemSessionStorage extends NativeSessionStorage
{
    private $path;
    private $data;

    public function __construct($path, array $options = array())
    {
        $this->path = $path;

        parent::__construct($options);
    }

    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        session_set_cookie_params(
            $this->options['lifetime'],
            $this->options['path'],
            $this->options['domain'],
            $this->options['secure'],
            $this->options['httponly']
        );

        if (!ini_get('session.use_cookies') && $this->options['id'] && $this->options['id'] != session_id()) {
            session_id($this->options['id']);
        }

        if (!session_id()) {
            session_id(hash('md5', uniqid(mt_rand(), true)));
        }

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $file = $this->path.'/'.session_id().'.session';

        $this->data = file_exists($file) ? unserialize(file_get_contents($file)) : array();
    }

    public function read($key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function remove($key)
    {
        $retval = $this->data[$key];

        unset($this->data[$key]);

        return $retval;
    }

    public function write($key, $data)
    {
        $this->data[$key] = $data;

        file_put_contents($this->path.'/'.session_id().'.session', serialize($this->data));
    }

    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->data = array();
        }
        return true;
    }
}
