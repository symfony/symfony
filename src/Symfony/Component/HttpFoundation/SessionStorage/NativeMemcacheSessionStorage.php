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

use Symfony\Component\HttpFoundation\AttributeBagInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * NativeMemcacheSessionStorage.
 *
 * Session based on native PHP memcache database handler.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeMemcacheSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * Constructor.
     *
     * @param string                $savePath   Path of memcache server.
     * @param array                 $options    Session configuration options.
     * @param AttributeBagInterface $attributes An AttributeBagInterface instance, (defaults null for default AttributeBag)
     * @param FlashBagInterface     $flashes    A FlashBagInterface instance (defaults null for default FlashBag)
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct($savePath = 'tcp://127.0.0.1:11211?persistent=0', array $options = array(), AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        if (!session_module_name('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" session module registered');
        }

        $this->savePath = $savePath;
        parent::__construct($attributes, $flashes, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'memcache');
        ini_set('session.save_path', $this->savePath);
    }
}
