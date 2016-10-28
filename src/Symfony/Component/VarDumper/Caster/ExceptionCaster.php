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
    public static $srcContext = 1;
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
        return self::filterExceptionArray($stub->class, $a, "\0Error\0", $filter);
    }

    public static function castException(\Exception $e, array $a, Stub $stub, $isNested, $filter = 0)
    {
        return self::filterExceptionArray($stub->class, $a, "\0Exception\0", $filter);
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

        if (isset($a[$xPrefix.'previous'], $a[$xPrefix.'trace'])) {
            $b = (array) $a[$xPrefix.'previous'];
            array_unshift($b[$xPrefix.'trace'], array(
                'function' => 'new '.get_class($a[$xPrefix.'previous']),
                'file' => $b[$prefix.'file'],
                'line' => $b[$prefix.'line'],
            ));
            $a[$xPrefix.'trace'] = new TraceStub($b[$xPrefix.'trace'], false, 0, -1 - count($a[$xPrefix.'trace']->value));
        }

        unset($a[$xPrefix.'previous'], $a[$prefix.'code'], $a[$prefix.'file'], $a[$prefix.'line']);

        return $a;
    }

    public static function castTraceStub(TraceStub $trace, array $a, Stub $stub, $isNested)
    {
        if (!$isNested) {
            return $a;
        }
        $stub->class = '';
        $stub->handle = 0;
        $frames = $trace->value;

        $a = array();
        $j = count($frames);
        if (0 > $i = $trace->sliceOffset) {
            $i = max(0, $j + $i);
        }
        if (!isset($trace->value[$i])) {
            return array();
        }
        $lastCall = isset($frames[$i]['function']) ? ' ==> '.(isset($frames[$i]['class']) ? $frames[0]['class'].$frames[$i]['type'] : '').$frames[$i]['function'].'()' : '';

        for ($j += $trace->numberingOffset - $i++; isset($frames[$i]); ++$i, --$j) {
            $call = isset($frames[$i]['function']) ? (isset($frames[$i]['class']) ? $frames[$i]['class'].$frames[$i]['type'] : '').$frames[$i]['function'].'()' : '???';

            $a[Caster::PREFIX_VIRTUAL.$j.'. '.$call.$lastCall] = new FrameStub(
                array(
                    'object' => isset($frames[$i]['object']) ? $frames[$i]['object'] : null,
                    'class' => isset($frames[$i]['class']) ? $frames[$i]['class'] : null,
                    'type' => isset($frames[$i]['type']) ? $frames[$i]['type'] : null,
                    'function' => isset($frames[$i]['function']) ? $frames[$i]['function'] : null,
                ) + $frames[$i - 1],
                $trace->keepArgs,
                true
            );

            $lastCall = ' ==> '.$call;
        }
        $a[Caster::PREFIX_VIRTUAL.$j.'. {main}'.$lastCall] = new FrameStub(
            array(
                'object' => null,
                'class' => null,
                'type' => null,
                'function' => '{main}',
            ) + $frames[$i - 1],
            $trace->keepArgs,
            true
        );
        if (null !== $trace->sliceLength) {
            $a = array_slice($a, 0, $trace->sliceLength, true);
        }

        return $a;
    }

    public static function castFrameStub(FrameStub $frame, array $a, Stub $stub, $isNested)
    {
        if (!$isNested) {
            return $a;
        }
        $f = $frame->value;
        $prefix = Caster::PREFIX_VIRTUAL;

        if (isset($f['file'], $f['line'])) {
            if (preg_match('/\((\d+)\)(?:\([\da-f]{32}\))? : (?:eval\(\)\'d code|runtime-created function)$/', $f['file'], $match)) {
                $f['file'] = substr($f['file'], 0, -strlen($match[0]));
                $f['line'] = (int) $match[1];
            }
            if (file_exists($f['file']) && 0 <= self::$srcContext) {
                $src[$f['file'].':'.$f['line']] = self::extractSource(explode("\n", file_get_contents($f['file'])), $f['line'], self::$srcContext);

                if (!empty($f['class']) && is_subclass_of($f['class'], 'Twig_Template') && method_exists($f['class'], 'getDebugInfo')) {
                    $template = isset($f['object']) ? $f['object'] : unserialize(sprintf('O:%d:"%s":0:{}', strlen($f['class']), $f['class']));

                    $templateName = $template->getTemplateName();
                    $templateSrc = method_exists($template, 'getSourceContext') ? $template->getSourceContext()->getCode() : (method_exists($template, 'getSource') ? $template->getSource() : '');
                    $templateInfo = $template->getDebugInfo();
                    if (isset($templateInfo[$f['line']])) {
                        if (method_exists($template, 'getSourceContext')) {
                            $templateName = $template->getSourceContext()->getPath() ?: $templateName;
                        }
                        if ($templateSrc) {
                            $templateSrc = explode("\n", $templateSrc);
                            $src[$templateName.':'.$templateInfo[$f['line']]] = self::extractSource($templateSrc, $templateInfo[$f['line']], self::$srcContext);
                        } else {
                            $src[$templateName] = $templateInfo[$f['line']];
                        }
                    }
                }
            } else {
                $src[$f['file']] = $f['line'];
            }
            $a[$prefix.'src'] = new EnumStub($src);
        }

        unset($a[$prefix.'args'], $a[$prefix.'line'], $a[$prefix.'file']);
        if ($frame->inTraceStub) {
            unset($a[$prefix.'class'], $a[$prefix.'type'], $a[$prefix.'function']);
        }
        foreach ($a as $k => $v) {
            if (!$v) {
                unset($a[$k]);
            }
        }
        if ($frame->keepArgs && isset($f['args'])) {
            $a[$prefix.'args'] = $f['args'];
        }

        return $a;
    }

    private static function filterExceptionArray($xClass, array $a, $xPrefix, $filter)
    {
        if (isset($a[$xPrefix.'trace'])) {
            $trace = $a[$xPrefix.'trace'];
            unset($a[$xPrefix.'trace']); // Ensures the trace is always last
        } else {
            $trace = array();
        }

        if (!($filter & Caster::EXCLUDE_VERBOSE)) {
            array_unshift($trace, array(
                'function' => $xClass ? 'new '.$xClass : null,
                'file' => $a[Caster::PREFIX_PROTECTED.'file'],
                'line' => $a[Caster::PREFIX_PROTECTED.'line'],
            ));
            $a[$xPrefix.'trace'] = new TraceStub($trace, self::$traceArgs);
        }
        if (empty($a[$xPrefix.'previous'])) {
            unset($a[$xPrefix.'previous']);
        }
        unset($a[$xPrefix.'string'], $a[Caster::PREFIX_DYNAMIC.'xdebug_message'], $a[Caster::PREFIX_DYNAMIC.'__destructorException']);

        return $a;
    }

    private static function extractSource(array $srcArray, $line, $srcContext)
    {
        $src = array();

        for ($i = $line - 1 - $srcContext; $i <= $line - 1 + $srcContext; ++$i) {
            $src[] = (isset($srcArray[$i]) ? $srcArray[$i] : '')."\n";
        }

        $ltrim = 0;
        do {
            $pad = null;
            for ($i = $srcContext << 1; $i >= 0; --$i) {
                if (isset($src[$i][$ltrim]) && "\r" !== ($c = $src[$i][$ltrim]) && "\n" !== $c) {
                    if (null === $pad) {
                        $pad = $c;
                    }
                    if ((' ' !== $c && "\t" !== $c) || $pad !== $c) {
                        break;
                    }
                }
            }
            ++$ltrim;
        } while (0 > $i && null !== $pad);

        if (--$ltrim) {
            foreach ($src as $i => $line) {
                $src[$i] = isset($line[$ltrim]) && "\r" !== $line[$ltrim] ? substr($line, $ltrim) : ltrim($line, " \t");
            }
        }

        return implode('', $src);
    }
}
