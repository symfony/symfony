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

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy;

/**
 * NativeProxySessionStorage can hook into an already configured session.
 *
 * @author Bilal Amarni <bilal.amarni@gmail.com>
 */
class NativeProxySessionStorage extends NativeSessionStorage
{
    public function __construct($handler = null)
    {
        $this->setSaveHandler($handler);
    }

    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        if (!$this->saveHandler->isActive()) {
            if (!session_start()) {
                throw new \RuntimeException('Failed to start the session');
            }

            $this->saveHandler->setActive(true);
        }

        $this->loadSession();

        return true;
    }

    public function setSaveHandler($saveHandler = null)
    {
        if (!$saveHandler) {
            $saveHandler = new NativeProxy();
        } elseif (!$saveHandler instanceof NativeProxy) {
            throw new \InvalidArgumentException(sprintf(
                'A NativeProxySessionStorage expects an instance of NativeProxy as its save handler, instance of "%s" given.', get_class($saveHandler)
            ));
        }

        // sets the save handler as active depending on the session status, assuming
        // the session is already started when using PHP 5.3 (as there is no reliable way
        // to check it for this PHP version)
        if (version_compare(phpversion(), '5.4.0', '>=') && PHP_SESSION_ACTIVE !== session_status()) {
            $saveHandler->setActive(false);
        } else {
            $saveHandler->setActive(true);
        }

        $this->saveHandler = $saveHandler;
    }

    public function clear()
    {
        // clear out the bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }

        // reconnect the bags to the session
        $this->loadSession();
    }

    protected function loadSession(array &$session = null)
    {
        if (null === $session) {
            $session = &$_SESSION;
        }

        foreach ($this->bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) ? $session[$key] : array();
            $bag->initialize($session[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
