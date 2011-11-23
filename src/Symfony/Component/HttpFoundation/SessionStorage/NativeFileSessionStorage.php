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
 * NativeFileSessionStorage.
 * 
 * Native session handler using PHP's built in file storage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NativeFileSessionStorage extends AbstractSessionStorage
{
    protected $savePath;
    
    public function __construct(FlashBagInterface $flashBag, array $options = array(), $savePath = null)
    {
        $this->savePath = $savePath;
        parent::__construct($flashBag, $options);
    }
    
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'files');
        if (!$this->savePath) {
            ini_set('session.save_path', $this->savePath);
        }
    }
}
