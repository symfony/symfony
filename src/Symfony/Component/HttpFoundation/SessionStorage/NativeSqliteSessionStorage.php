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
 * NativeSqliteSessionStorage.
 *
 * Session based on native PHP sqlite database handler.
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
    private $dbPath;

    /**
     * Constructor.
     *
     * @param string $dbPath
     * @param array  $options
     */
    public function __construct($dbPath, array $options = array())
    {
        if (!session_module_name('sqlite')) {
            throw new \RuntimeException('PHP does not have "sqlite" session module registered');
        }

        $this->dbPath = $dbPath;
        parent::__construct($options);
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
