<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Exception\ThrowingCasterException;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts common Exception classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ExceptionCaster
{
    public static $traceArgs = true;
    public static $errorTypes = array(
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
    );

    public static function castException(\Exception $e, array $a, Stub $stub, $isNested)
    {
        $trace = $a["\0Exception\0trace"];
        unset($a["\0Exception\0trace"]); // Ensures the trace is always last

        static::filterTrace($trace, static::$traceArgs);

        if (null !== $trace) {
            $a["\0Exception\0trace"] = $trace;
        }
        if (empty($a["\0Exception\0previous"])) {
            unset($a["\0Exception\0previous"]);
        }
        unset($a["\0Exception\0string"], $a["\0+\0xdebug_message"], $a["\0+\0__destructorException"]);

        return $a;
    }

    public static function castErrorException(\ErrorException $e, array $a, Stub $stub, $isNested)
    {
        if (isset($a[$s = "\0*\0severity"], self::$errorTypes[$a[$s]])) {
            $a[$s] = new ConstStub(self::$errorTypes[$a[$s]], $a[$s]);
        }

        return $a;
    }

    public static function castThrowingCasterException(ThrowingCasterException $e, array $a, Stub $stub, $isNested)
    {
        $b = (array) $a["\0Exception\0previous"];

        array_splice($b["\0Exception\0trace"], count($a["\0Exception\0trace"]));

        $t = static::$traceArgs;
        static::$traceArgs = false;
        $b = static::castException($a["\0Exception\0previous"], $b, $stub, $isNested);
        static::$traceArgs = $t;

        if (empty($a["\0*\0message"])) {
            $a["\0*\0message"] = "Unexpected exception thrown from a caster: ".get_class($a["\0Exception\0previous"]);
        }

        if (isset($b["\0*\0message"])) {
            $a["\0~\0message"] = $b["\0*\0message"];
        }
        if (isset($b["\0*\0file"])) {
            $a["\0~\0file"] = $b["\0*\0file"];
        }
        if (isset($b["\0*\0line"])) {
            $a["\0~\0line"] = $b["\0*\0line"];
        }
        if (isset($b["\0Exception\0trace"])) {
            $a["\0~\0trace"] = $b["\0Exception\0trace"];
        }

        unset($a["\0Exception\0trace"], $a["\0Exception\0previous"], $a["\0*\0code"], $a["\0*\0file"], $a["\0*\0line"]);

        return $a;
    }

    public static function filterTrace(&$trace, $dumpArgs, $offset = 0)
    {
        if (0 > $offset || empty($trace[$offset])) {
            return $trace = null;
        }

        $t = $trace[$offset];

        if (empty($t['class']) && isset($t['function'])) {
            if ('user_error' === $t['function'] || 'trigger_error' === $t['function']) {
                ++$offset;
            }
        }

        if ($offset) {
            array_splice($trace, 0, $offset);
        }

        foreach ($trace as &$t) {
            $t = array(
                'call' => (isset($t['class']) ? $t['class'].$t['type'] : '').$t['function'].'()',
                'file' => isset($t['line']) ? "{$t['file']}:{$t['line']}" : '',
                'args' => &$t['args'],
            );

            if (!isset($t['args']) || !$dumpArgs) {
                unset($t['args']);
            }
        }
    }
}
