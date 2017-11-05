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

@trigger_error('The '.__NAMESPACE__.'\NativeProxy class is deprecated since version 3.4 and will be removed in 4.0. Use your session handler implementation directly.', E_USER_DEPRECATED);

/**
 * This proxy is built-in session handlers in PHP 5.3.x.
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use your session handler implementation directly.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeProxy extends AbstractProxy
{
    public function __construct()
    {
        // this makes an educated guess as to what the handler is since it should already be set.
        $this->saveHandlerName = ini_get('session.save_handler');
    }

    /**
     * Returns true if this handler wraps an internal PHP session save handler using \SessionHandler.
     *
     * @return bool False
     */
    public function isWrapper()
    {
        return false;
    }
}
