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
 * NativeMemcachedSessionStorage.
 *
 * Session based on native PHP memcached database handler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NativeMemcachedSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * Constructor.
     *
     * @param array  $options
     * @param string $savePath Comma separated list of servers: e.g. memcache1.example.com:11211,memcache2.example.com:11211
     */
    public function __construct($savePath = '127.0.0.1:11211', array $options = array())
    {
        if (!session_module_name('memcached')) {
            throw new \RuntimeException('PHP does not have "memcached" session module registered');
        }

        $this->savePath = $savePath;
        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'memcached');
        ini_set('session.save_path', $this->savePath);
    }
}
