<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

// Load the global dump() function
require_once __DIR__.'/Resources/functions/dump.php';

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class VarDumper
{
    private static $handler;
    private static $locked = false;

    public static function dump($var)
    {
        if (null === self::$handler) {
            $cloner = new VarCloner();
            $dumper = self::getDefaultDumper();
            self::$handler = function ($var) use ($cloner, $dumper) {
                $dumper->dump($cloner->cloneVar($var));
            };
        }

        return call_user_func(self::$handler, $var);
    }

    /**
     * @final since 4.1
     */
    public static function setHandler(callable $callable = null/*, bool $lock = false*/)/*: ?callable*/
    {
        $lock = \func_num_args() > 1 ? func_get_arg(1) : false;
        $prevHandler = self::$handler;

        if (self::$locked) {
            return $prevHandler;
        }
        if ($lock) {
            self::$locked = true;
        }

        self::$handler = $callable;

        return $prevHandler;
    }

    /**
     * @final
     */
    public static function getDefaultDumper(): DataDumperInterface
    {
        return \in_array(PHP_SAPI, array('cli', 'phpdbg'), true) ? new CliDumper() : new HtmlDumper();
    }
}
