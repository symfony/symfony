<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

/**
 * NativeRedisSessionStorage.
 *
 * Driver for the redis session save hadlers provided by the redis PHP extension.
 *
 * @see https://github.com/nicolasff/phpredis
 *
 * @author Andrej Hudec <pulzarraider@gmail.com>
 */
class NativeRedisSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * Constructor.
     *
     * @param string $savePath Path of redis server.
     * @param array  $options  Session configuration options.
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct($savePath = 'tcp://127.0.0.1:6379?persistent=0', array $options = array())
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('PHP does not have "redis" session module registered');
        }

        $this->savePath = $savePath;
        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $this->savePath);
    }
}
