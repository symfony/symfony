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
 * @deprecated since version 3.4, to be removed in 4.0. Use \SessionHandler instead.
 * @see http://php.net/sessionhandler
 */
class NativeSessionHandler extends \SessionHandler
{
    public function __construct()
    {
        @trigger_error('The '.__NAMESPACE__.'\NativeSessionHandler class is deprecated since version 3.4 and will be removed in 4.0. Use the \SessionHandler class instead.', E_USER_DEPRECATED);
    }
}
