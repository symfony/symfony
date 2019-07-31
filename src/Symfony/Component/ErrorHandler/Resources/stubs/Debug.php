<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

if (!class_exists(Debug::class, false)) {
    class_alias(\Symfony\Component\ErrorHandler\Debug::class, Debug::class);
}

if (false) {
    /**
     * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\Debug instead.
     */
    class Debug extends \Symfony\Component\ErrorHandler\Debug
    {
    }
}
