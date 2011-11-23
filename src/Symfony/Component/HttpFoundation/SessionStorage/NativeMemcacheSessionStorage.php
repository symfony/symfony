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

use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * NativeMemcacheSessionStorage.
 * 
 * Session based on native PHP sqlite2 database handler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NativeMemcacheSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    protected $savePath;
    
    public function __construct(FlashBagInterface $flashBag, array $options = array(), $savePath = 'tcp://127.0.0.1:11211?persistent=1&weight=2&timeout=2&retry_interval=10')
    {
        if (!session_module_name('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" session module registered');
        }
        
        $this->savePath = $savePath;
        parent::__construct($flashBag, $options);
    }
    
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'memcache');
        ini_set('session.save_path', $this->savePath);
    }
}
