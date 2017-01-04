<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Compat\Util;

if (class_exists('PHPUnit\Util\ErrorHandler')) {
    class ErrorHandler extends \PHPUnit\Util\ErrorHandler
    {}
} else {
    class ErrorHandler extends \PHPUnit_Util_ErrorHandler
    {}
}
