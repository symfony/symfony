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
 * NativeSqliteSessionStorage.
 *
 * Session based on native PHP sqlite database handler.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeSqliteSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $dbPath;

    /**
     * Constructor.
     *
     * @param string                $dbPath     Path to SQLite database file.
     * @param array                 $options    Session configuration options.
     * @param AttributeBagInterface $attributes An AttributeBagInterface instance, (defaults null for default AttributeBag)
     * @param FlashBagInterface     $flashes    A FlashBagInterface instance (defaults null for defaul FlashBag)
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct($dbPath, array $options = array(), AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        if (!session_module_name('sqlite')) {
            throw new \RuntimeException('PHP does not have "sqlite" session module registered');
        }

        $this->dbPath = $dbPath;
        parent::__construct($attributes, $flashes, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'sqlite');
        ini_set('session.save_path', $this->dbPath);
    }
}
