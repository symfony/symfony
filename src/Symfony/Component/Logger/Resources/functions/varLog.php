<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Logger\Logger;

if (!function_exists('varLog')) {
    function varLog($var, $channel = 'log')
    {
            Logger::varLog($var, $channel);
    }
}
