<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Proxy;

/**
 * SessionHandler proxy.
 */
class SessionHandlerProxy extends AbstractProxy implements \SessionHandlerInterface
{
    /**
     * @var \SessionHandlerInterface
     */
    protected $handler;

    /**
     * Constructor.
     *
     * @param \SessionHandlerInterface $handler
     */
    public function __construct(\SessionHandlerInterface $handler)
    {
        $this->handler = $handler;
        $this->wrapper = (bool)(class_exists('SessionHandler') && $handler instanceof \SessionHandler);
        $this->saveHandlerName = $this->wrapper ? ini_get('session.save_handler') : 'user';
    }

    // \SessionHandlerInterface

    /**
     * {@inheritdoc}
     */
    function open($savePath, $sessionName)
    {
        $return = (bool)$this->handler->open($savePath, $sessionName);

        if (true === $return) {
            $this->active = true;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    function close()
    {
        $this->active = false;

        return (bool)$this->handler->close();
    }

    /**
     * {@inheritdoc}
     */
    function read($id)
    {
        return (string)$this->handler->read($id);
    }

    /**
     * {@inheritdoc}
     */
    function write($id, $data)
    {
        return (bool)$this->handler->write($id, $data);
    }

    /**
     * {@inheritdoc}
     */
    function destroy($id)
    {
        return (bool)$this->handler->destroy($id);
    }

    /**
     * {@inheritdoc}
     */
    function gc($maxlifetime)
    {
        return (bool)$this->handler->gc($maxlifetime);
    }
}
