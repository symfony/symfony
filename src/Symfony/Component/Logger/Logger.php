<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Logger;

use symfony\symfony\src\Symfony\Component\Logger\Handler\SimpleFileLogger;
use Psr\Log\LoggerInterface;

// Load the global varLog() function
require_once __DIR__.'/Resources/functions/varLog.php';

class Logger
{
    private static $logger;
    private static $preArgs = array();
    private static $postArgs = array();

    public static function varLog($var, $channel)
    {
        if (null === self::$logger) {
            self::$logger = new SimpleFileLogger();
        }

        $params = array_merge(self::$preArgs, array($var), self::$postArgs);

        return call_user_func_array(array(self::$logger, $channel), $params);
    }

    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * @param mixed $postArgs
     */
    public function setPostArgs($postArgs)
    {
        self::$postArgs = $postArgs;
    }

    /**
     * @param mixed $preArgs
     */
    public function setPreArgs($preArgs)
    {
        self::$preArgs = $preArgs;
    }
}
