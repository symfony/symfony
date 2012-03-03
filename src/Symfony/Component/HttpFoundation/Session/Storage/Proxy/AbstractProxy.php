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
 * AbstractProxy.
 */
abstract class AbstractProxy
{
    /**
     * Flag if handler wraps an internal PHP session handler (using \SessionHandler).
     *
     * @var boolean
     */
    protected $wrapper = false;

    /**
     * @var boolean
     */
    protected $active = false;

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

    public function isSessionHandlerInterface()
    {
        return (bool)($this instanceof \SessionHandlerInterface);
    }

    /**
     * Returns true if this handler wraps an internal PHP session save handler using \SessionHandler.
     *
     * @return bool
     */
    public function isWrapper()
    {
        return $this->wrapper;
    }

    /**
     * Has a session started?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Sets the active flag.
     *
     * @param bool $flag
     */
    public function setActive($flag)
    {
        $this->active = (bool)$flag;
    }
}
