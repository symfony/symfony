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
 * NullSessionHandler.
 *
 * Can be used in unit testing or in a situations where persisted sessions are not desired.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 *
 * @since v2.1.0
 */
class NullSessionHandler implements \SessionHandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function read($sessionId)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function write($sessionId, $data)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function destroy($sessionId)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function gc($lifetime)
    {
        return true;
    }
}
