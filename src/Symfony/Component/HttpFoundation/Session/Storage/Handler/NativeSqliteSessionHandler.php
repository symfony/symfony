<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/**
 * NativeSqliteSessionHandler.
 *
 * Driver for the sqlite session save handler provided by the SQLite PHP extension.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeSqliteSessionHandler extends NativeSessionHandler
{
    /**
     * Constructor.
     *
     * @param string $savePath Path to SQLite database file itself.
     *
     * @throws \RuntimeException When the sqlite extension is not available
     */
    public function __construct($savePath)
    {
        if (!extension_loaded('sqlite')) {
            throw new \RuntimeException('PHP does not have "sqlite" session module registered');
        }

        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        ini_set('session.save_handler', 'sqlite');
        ini_set('session.save_path', $savePath);
    }
}
