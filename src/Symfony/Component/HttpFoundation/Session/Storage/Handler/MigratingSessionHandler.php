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
 * Migrating session handler for migrating from one handler to another. It reads
 * from the current handler and writes both the current and new ones.
 *
 * It ignores errors from the new handler.
 *
 * @author Ross Motley <ross.motley@amara.com>
 * @author Oliver Radwell <oliver.radwell@amara.com>
 */
class MigratingSessionHandler implements \SessionHandlerInterface
{
    private $currentHandler;
    private $writeOnlyHandler;

    public function __construct(\SessionHandlerInterface $currentHandler, \SessionHandlerInterface $writeOnlyHandler)
    {
        $this->currentHandler = $currentHandler;
        $this->writeOnlyHandler = $writeOnlyHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $result = $this->currentHandler->close();
        $this->writeOnlyHandler->close();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $result = $this->currentHandler->destroy($sessionId);
        $this->writeOnlyHandler->destroy($sessionId);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        $result = $this->currentHandler->gc($maxlifetime);
        $this->writeOnlyHandler->gc($maxlifetime);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionId)
    {
        $result = $this->currentHandler->open($savePath, $sessionId);
        $this->writeOnlyHandler->open($savePath, $sessionId);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        // No reading from new handler until switch-over
        return $this->currentHandler->read($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        $result = $this->currentHandler->write($sessionId, $sessionData);
        $this->writeOnlyHandler->write($sessionId, $sessionData);

        return $result;
    }
}
