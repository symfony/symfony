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
 * NativeSqliteSessionStorage.
 * 
 * Session based on native PHP sqlite2 database handler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NativeSqliteSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    protected $dbPath;
    
    public function __construct(FlashBagInterface $flashBag, array $options = array(), $dbPath = '/tmp/sf2_sqlite_sess.db')
    {
        if (!session_module_name('sqlite')) {
            throw new \RuntimeException('PHP does not have "sqlite" session module registered');
        }
        
        $this->dbPath = $dbPath;
        parent::__construct($flashBag, $options);
    }
    
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'sqlite');
        ini_set('session.save_path', $this->dbPath);
    }
}
