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

use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class MarshallingSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private $handler;
    private $marshaller;

    public function __construct(AbstractSessionHandler $handler, MarshallerInterface $marshaller)
    {
        $this->handler = $handler;
        $this->marshaller = $marshaller;
    }

    /**
     * @return bool
     */
    public function open(string $savePath, string $name)
    {
        return $this->handler->open($savePath, $name);
    }

    /**
     * @return bool
     */
    public function close()
    {
        return $this->handler->close();
    }

    /**
     * @return bool
     */
    public function destroy(string $sessionId)
    {
        return $this->handler->destroy($sessionId);
    }

    /**
     * @return bool
     */
    public function gc(int $maxlifetime)
    {
        return $this->handler->gc($maxlifetime);
    }

    /**
     * @return string
     */
    public function read(string $sessionId)
    {
        return $this->marshaller->unmarshall($this->handler->read($sessionId));
    }

    /**
     * @return bool
     */
    public function write(string $sessionId, string $data)
    {
        $failed = [];
        $marshalledData = $this->marshaller->marshall(['data' => $data], $failed);

        if (isset($failed['data'])) {
            return false;
        }

        return $this->handler->write($sessionId, $marshalledData['data']);
    }

    /**
     * @return bool
     */
    public function validateId(string $sessionId)
    {
        return $this->handler->validateId($sessionId);
    }

    /**
     * @return bool
     */
    public function updateTimestamp(string $sessionId, string $data)
    {
        return $this->handler->updateTimestamp($sessionId, $data);
    }
}
