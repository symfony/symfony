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
 * NullSessionStorage.
 *
 * Can be used in unit testing or in a sitation where persisted sessions are not desired.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NullSessionStorage extends AbstractSessionStorage implements SessionSaveHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Close session.
     *
     * @return boolean
     */
    public function closeSession()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function readSession($sessionId)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function writeSession($sessionId, $data)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroySession($sessionId)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gcSession($lifetime)
    {
        return true;
    }
}
