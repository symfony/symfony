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

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.4 and will be removed in 4.0. Implement `SessionUpdateTimestampHandlerInterface` or extend `AbstractSessionHandler` instead.', WriteCheckSessionHandler::class), E_USER_DEPRECATED);

/**
 * Wraps another SessionHandlerInterface to only write the session when it has been modified.
 *
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0. Implement `SessionUpdateTimestampHandlerInterface` or extend `AbstractSessionHandler` instead.
 */
class WriteCheckSessionHandler implements \SessionHandlerInterface
{
    private $wrappedSessionHandler;

    /**
     * @var array sessionId => session
     */
    private $readSessions;

    public function __construct(\SessionHandlerInterface $wrappedSessionHandler)
    {
        $this->wrappedSessionHandler = $wrappedSessionHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->wrappedSessionHandler->close();
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return $this->wrappedSessionHandler->destroy($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        return $this->wrappedSessionHandler->gc($maxlifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return $this->wrappedSessionHandler->open($savePath, $sessionName);
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $session = $this->wrappedSessionHandler->read($sessionId);

        $this->readSessions[$sessionId] = $session;

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        if (isset($this->readSessions[$sessionId]) && $data === $this->readSessions[$sessionId]) {
            return true;
        }

        return $this->wrappedSessionHandler->write($sessionId, $data);
    }
}
