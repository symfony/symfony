<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler;

function headers_sent()
{
    return false;
}

function header($str, $replace = true, $status = null)
{
    Tests\testHeader($str, $replace, $status);
}

namespace Symfony\Component\ErrorHandler\Tests;

function testHeader()
{
    static $headers = [];

    if (!$h = \func_get_args()) {
        $h = $headers;
        $headers = [];

        return $h;
    }

    $headers[] = \func_get_args();
}
