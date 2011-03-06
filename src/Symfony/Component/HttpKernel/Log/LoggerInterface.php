<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Log;

/**
 * LoggerInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface LoggerInterface
{
    function log($message, $priority);

    function emerg($message);

    function alert($message);

    function crit($message);

    function err($message);

    function warn($message);

    function notice($message);

    function info($message);

    function debug($message);
}
