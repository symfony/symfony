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
 * NativeProxy.
 *
 * This proxy is built-in session handlers in PHP 5.3.x
 */
class NativeProxy extends AbstractProxy
{
    /**
     * Constructor.
     *
     * @param $handler
     */
    public function __construct($handler)
    {
        if (version_compare(phpversion(), '5.4.0', '>=') && $handler instanceof \SessionHandlerInterface) {
            throw new \InvalidArgumentException('This proxy is only for PHP 5.3 and not for instances of \SessionHandler or \SessionHandlerInterface');
        }
        
        $this->handler = $handler;
        $this->saveHandlerName = ini_get('session.save_handler');
    }

    /**
     * Returns true if this handler wraps an internal PHP session save handler using \SessionHandler.
     *
     * @return bool False.
     */
    public function isWrapper()
    {
        return false;
    }
}
