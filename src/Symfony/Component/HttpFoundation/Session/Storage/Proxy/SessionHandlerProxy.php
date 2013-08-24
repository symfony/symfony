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
 *
 * @author Drak <drak@zikula.org>
 *
 * @since v2.1.0
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
     *
     * @since v2.1.0
     */
    public function __construct(\SessionHandlerInterface $handler)
    {
        $this->handler = $handler;
        $this->wrapper = ($handler instanceof \SessionHandler);
        $this->saveHandlerName = $this->wrapper ? ini_get('session.save_handler') : 'user';
    }

    // \SessionHandlerInterface

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function open($savePath, $sessionName)
    {
        $return = (bool) $this->handler->open($savePath, $sessionName);

        if (true === $return) {
            $this->active = true;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function close()
    {
        $this->active = false;

        return (bool) $this->handler->close();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function read($id)
    {
        return (string) $this->handler->read($id);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function write($id, $data)
    {
        return (bool) $this->handler->write($id, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function destroy($id)
    {
        return (bool) $this->handler->destroy($id);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function gc($maxlifetime)
    {
        return (bool) $this->handler->gc($maxlifetime);
    }
}
