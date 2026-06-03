<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Exporter
{
    public static function export($value, $indent = '')
    {
        switch (true) {
            case \is_int($value) || \is_float($value): return var_export($value, true);
            case [] === $value: return '[]';
            case false === $value: return 'false';
            case true === $value: return 'true';
            case null === $value: return 'null';
            case '' === $value: return "''";
            case $value instanceof \UnitEnum: return '\\'.ltrim(var_export($value, true), '\\');
        }

        $subIndent = $indent.'    ';

        if (\is_string($value)) {
            $code = \sprintf("'%s'", addcslashes($value, "'\\"));

            $code = preg_replace_callback("/((?:[\\0\\r\\n]|\u{202A}|\u{202B}|\u{202D}|\u{202E}|\u{2066}|\u{2067}|\u{2068}|\u{202C}|\u{2069})++)(.)/", static function ($m) use ($subIndent) {
                $m[1] = \sprintf('\'."%s".\'', str_replace(
                    ["\0", "\r", "\n", "\u{202A}", "\u{202B}", "\u{202D}", "\u{202E}", "\u{2066}", "\u{2067}", "\u{2068}", "\u{202C}", "\u{2069}", '\n\\'],
                    ['\0', '\r', '\n', '\u{202A}', '\u{202B}', '\u{202D}', '\u{202E}', '\u{2066}', '\u{2067}', '\u{2068}', '\u{202C}', '\u{2069}', '\n"'."\n".$subIndent.'."\\'],
                    $m[1]
                ));

                if ("'" === $m[2]) {
                    return substr($m[1], 0, -2);
                }

                if (str_ends_with($m[1], 'n".\'')) {
                    return substr_replace($m[1], "\n".$subIndent.".'".$m[2], -2);
                }

                return $m[1].$m[2];
            }, $code, -1, $count);

            if ($count && str_starts_with($code, "''.")) {
                $code = substr($code, 3);
            }

            return $code;
        }

        if (!\is_array($value)) {
            throw new \UnexpectedValueException(\sprintf('Cannot export value of type "%s".', get_debug_type($value)));
        }
        $j = -1;
        $code = '';
        $isFlat = '' !== $indent;
        $size = 0;
        foreach ($value as $k => $v) {
            $code .= $subIndent;
            if (!\is_int($k) || 1 !== $k - $j) {
                $code .= self::export($k, $subIndent).' => ';
                ++$size;
            }
            if (\is_int($k) && $k > $j) {
                $j = $k;
            }
            if (\is_array($v)) {
                $isFlat = false;
            }
            $code .= self::export($v, $subIndent).",\n";
            ++$size;
        }

        if (!$isFlat) {
            return "[\n".$code.$indent.']';
        }

        // Single-line: content fits within the 20-items budget
        if ($size <= 20) {
            $j = -1;
            $code = '[';
            foreach ($value as $k => $v) {
                if ('[' !== $code) {
                    $code .= ', ';
                }
                if (!\is_int($k) || 1 !== $k - $j) {
                    $code .= self::export($k, $indent).' => ';
                }
                if (\is_int($k) && $k > $j) {
                    $j = $k;
                }
                $code .= self::export($v, $indent);
            }

            return $code.']';
        }

        // Multi-line wrapped: pack values onto each line; before appending the next
        // value, check that the line would still hold <= 20 items.
        $j = -1;
        $code = '';
        $line = '';
        $lineSize = 0;
        foreach ($value as $k => $v) {
            $part = '';
            $partSize = 1;
            if (!\is_int($k) || 1 !== $k - $j) {
                $part .= self::export($k, $subIndent).' => ';
                ++$partSize;
            }
            if (\is_int($k) && $k > $j) {
                $j = $k;
            }
            $part .= self::export($v, $subIndent).',';

            if ('' !== $line && $lineSize + $partSize > 20) {
                $code .= $subIndent.$line."\n";
                $line = $part;
                $lineSize = $partSize;
            } else {
                $line .= '' === $line ? $part : ' '.$part;
                $lineSize += $partSize;
            }
        }
        if ('' !== $line) {
            $code .= $subIndent.$line."\n";
        }

        return "[\n".$code.$indent.']';
    }
}
