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
    public function sessionOpen($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Close session.
     *
     * @return boolean
     */
    public function sessionClose()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function sessionRead($sessionId)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function sessionWrite($sessionId, $data)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function sessionDestroy($sessionId)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function sessionGc($lifetime)
    {
        return true;
    }
}
