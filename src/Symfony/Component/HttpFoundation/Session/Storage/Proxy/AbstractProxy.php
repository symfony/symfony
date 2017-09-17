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
 * @deprecated since version 3.4, to be removed in 4.0. Use SessionHandlerProxy instead.
 *
 * @author Drak <drak@zikula.org>
 */
abstract class AbstractProxy
{
    /**
     * Flag if handler wraps an internal PHP session handler (using \SessionHandler).
     *
     * @deprecated since version 3.4 and will be removed in 4.0.
     *
     * @var bool
     */
    protected $wrapper = false;

    /**
     * @var string
     */
    protected $saveHandlerName;

    /**
     * Gets the session.save_handler name.
     *
     * @return string
     */
    public function getSaveHandlerName()
    {
        return $this->saveHandlerName;
    }

    /**
     * Is this proxy handler and instance of \SessionHandlerInterface.
     *
     * @deprecated since version 3.4 and will be removed in 4.0.
     *
     * @return bool
     */
    public function isSessionHandlerInterface()
    {
        @trigger_error('isSessionHandlerInterface() is deprecated since version 3.4 and will be removed in 4.0. A session handler proxy should always implement \SessionHandlerInterface.', E_USER_DEPRECATED);

        return $this instanceof \SessionHandlerInterface;
    }

    /**
     * Returns true if this handler wraps an internal PHP session save handler using \SessionHandler.
     *
     * @deprecated since version 3.4 and will be removed in 4.0.
     *
     * @return bool
     */
    public function isWrapper()
    {
        @trigger_error('isWrapper() is deprecated since version 3.4 and will be removed in 4.0. You should explicitly check if the handler proxy wraps a \SessionHandler instance.', E_USER_DEPRECATED);

        return $this->wrapper;
    }

    /**
     * Has a session started?
     *
     * @return bool
     */
    public function isActive()
    {
        return \PHP_SESSION_ACTIVE === session_status();
    }

    /**
     * Gets the session ID.
     *
     * @return string
     */
    public function getId()
    {
        return session_id();
    }

    /**
     * Sets the session ID.
     *
     * @param string $id
     *
     * @throws \LogicException
     */
    public function setId($id)
    {
        if ($this->isActive()) {
            throw new \LogicException('Cannot change the ID of an active session');
        }

        session_id($id);
    }

    /**
     * Gets the session name.
     *
     * @return string
     */
    public function getName()
    {
        return session_name();
    }

    /**
     * Sets the session name.
     *
     * @param string $name
     *
     * @throws \LogicException
     */
    public function setName($name)
    {
        if ($this->isActive()) {
            throw new \LogicException('Cannot change the name of an active session');
        }

        session_name($name);
    }
}
