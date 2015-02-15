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

<<<<<<< HEAD
// Adds SessionHandler functionality if available.
// @see http://php.net/sessionhandler
if (PHP_VERSION_ID >= 50400) {
    class NativeSessionHandler extends \SessionHandler
    {
    }
} else {
    class NativeSessionHandler
    {
    }
=======
/**
 * Adds SessionHandler functionality if available.
 *
 * @see http://php.net/sessionhandler
 */
class NativeSessionHandler extends \SessionHandler
{
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
}
