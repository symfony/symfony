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

    public static function castError(\Error $e, array $a, Stub $stub, $isNested, $filter = 0)
    {
        return $e instanceof \Exception ? $a : self::filterExceptionArray($a, "\0Error\0", $filter);
    }

    public static function castException(\Exception $e, array $a, Stub $stub, $isNested, $filter = 0)
    {
        return self::filterExceptionArray($a, "\0Exception\0", $filter);
    }

    public static function castErrorException(\ErrorException $e, array $a, Stub $stub, $isNested)
    {
        if (isset($a[$s = Caster::PREFIX_PROTECTED.'severity'], self::$errorTypes[$a[$s]])) {
            $a[$s] = new ConstStub(self::$errorTypes[$a[$s]], $a[$s]);
        }

        return $a;
    }

    public static function castThrowingCasterException(ThrowingCasterException $e, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_PROTECTED;
        $xPrefix = "\0Exception\0";

        if (isset($a[$xPrefix.'previous'], $a[$xPrefix.'trace'][0])) {
            $b = (array) $a[$xPrefix.'previous'];
            $b[$xPrefix.'trace'][0] += array(
                'file' => $b[$prefix.'file'],
                'line' => $b[$prefix.'line'],
            );
            array_splice($b[$xPrefix.'trace'], -1 - count($a[$xPrefix.'trace']));
            static::filterTrace($b[$xPrefix.'trace'], false);
            $a[Caster::PREFIX_VIRTUAL.'trace'] = $b[$xPrefix.'trace'];
        }

        unset($a[$xPrefix.'trace'], $a[$xPrefix.'previous'], $a[$prefix.'code'], $a[$prefix.'file'], $a[$prefix.'line']);

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

    private static function filterExceptionArray(array $a, $xPrefix, $filter)
    {
        if (isset($a[$xPrefix.'trace'])) {
            $trace = $a[$xPrefix.'trace'];
            unset($a[$xPrefix.'trace']); // Ensures the trace is always last
        } else {
            $trace = array();
        }

        if (!($filter & Caster::EXCLUDE_VERBOSE)) {
            static::filterTrace($trace, static::$traceArgs);

            if (null !== $trace) {
                $a[$xPrefix.'trace'] = $trace;
            }
        }
        if (empty($a[$xPrefix.'previous'])) {
            unset($a[$xPrefix.'previous']);
        }
        unset($a[$xPrefix.'string'], $a[Caster::PREFIX_DYNAMIC.'xdebug_message'], $a[Caster::PREFIX_DYNAMIC.'__destructorException']);

        return $a;
    }
}
