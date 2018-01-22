<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\DumperInterface;

/**
 * Abstract mechanism for dumping a Data object.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractDumper implements DataDumperInterface, DumperInterface
{
    public static $defaultOutput = 'php://output';

    protected $line = '';
    protected $lineDumper;
    protected $outputStream;
    protected $decimalPoint; // This is locale dependent
    protected $indentPad = '  ';

    private $charset;
    private $charsetConverter;

    /**
     * @param callable|resource|string|null $output  A line dumper callable, an opened stream or an output path, defaults to static::$defaultOutput
     * @param string                        $charset The default character encoding to use for non-UTF8 strings
     */
    public function __construct($output = null, $charset = null)
    {
        $this->setCharset($charset ?: ini_get('php.output_encoding') ?: ini_get('default_charset') ?: 'UTF-8');
        $this->decimalPoint = localeconv();
        $this->decimalPoint = $this->decimalPoint['decimal_point'];
        $this->setOutput($output ?: static::$defaultOutput);
        if (!$output && is_string(static::$defaultOutput)) {
            static::$defaultOutput = $this->outputStream;
        }
    }

    /**
     * Sets the output destination of the dumps.
     *
     * @param callable|resource|string $output A line dumper callable, an opened stream or an output path
     *
     * @return callable|resource|string The previous output destination
     */
    public function setOutput($output)
    {
        $prev = null !== $this->outputStream ? $this->outputStream : $this->lineDumper;

        if (is_callable($output)) {
            $this->outputStream = null;
            $this->lineDumper = $output;
        } else {
            if (is_string($output)) {
                $output = fopen($output, 'wb');
            }
            $this->outputStream = $output;
            $this->lineDumper = array($this, 'echoLine');
        }

        return $prev;
    }

    /**
     * Sets the default character encoding to use for non-UTF8 strings.
     *
     * @param string $charset The default character encoding to use for non-UTF8 strings
     *
     * @return string The previous charset
     */
    public function setCharset($charset)
    {
        $prev = $this->charset;
        $charset = strtoupper($charset);
        $charset = null === $charset || 'UTF-8' === $charset || 'UTF8' === $charset ? 'CP1252' : $charset;

        if ($prev === $charset) {
            return $prev;
        }
        $this->charsetConverter = 'fallback';
        $supported = true;
        set_error_handler(function () use (&$supported) { $supported = false; });

        if (function_exists('mb_encoding_aliases') && mb_encoding_aliases($charset)) {
            $this->charset = $charset;
            $this->charsetConverter = 'mbstring';
        } elseif (function_exists('iconv')) {
            $supported = true;
            iconv($charset, 'UTF-8', '');
            if ($supported) {
                $this->charset = $charset;
                $this->charsetConverter = 'iconv';
            }
        }
        if ('fallback' === $this->charsetConverter) {
            $this->charset = 'ISO-8859-1';
        }
        restore_error_handler();

        return $prev;
    }

    /**
     * Sets the indentation pad string.
     *
     * @param string $pad A string that will be prepended to dumped lines, repeated by nesting level
     *
     * @return string The previous indent pad
     */
    public function setIndentPad($pad)
    {
        $prev = $this->indentPad;
        $this->indentPad = $pad;

        return $prev;
    }

    /**
     * Dumps a Data object.
     *
     * @param Data                          $data   A Data object
     * @param callable|resource|string|null $output A line dumper callable, an opened stream or an output path
     */
    public function dump(Data $data, $output = null)
    {
        $this->decimalPoint = localeconv();
        $this->decimalPoint = $this->decimalPoint['decimal_point'];

        $exception = null;
        if ($output) {
            $prevOutput = $this->setOutput($output);
        }
        try {
            $data->dump($this);
            $this->dumpLine(-1);
        } catch (\Exception $exception) {
            // Re-thrown below
        } catch (\Throwable $exception) {
            // Re-thrown below
        }
        if ($output) {
            $this->setOutput($prevOutput);
        }
        if (null !== $exception) {
            throw $exception;
        }
    }

    /**
     * Dumps the current line.
     *
     * @param int $depth The recursive depth in the dumped structure for the line being dumped,
     *                   or -1 to signal the end-of-dump to the line dumper callable
     */
    protected function dumpLine($depth)
    {
        call_user_func($this->lineDumper, $this->line, $depth, $this->indentPad);
        $this->line = '';
    }

    /**
     * Generic line dumper callback.
     *
     * @param string $line      The line to write
     * @param int    $depth     The recursive depth in the dumped structure
     * @param string $indentPad The line indent pad
     */
    protected function echoLine($line, $depth, $indentPad)
    {
        if (-1 !== $depth) {
            fwrite($this->outputStream, str_repeat($indentPad, $depth).$line."\n");
        }
    }

    /**
     * Converts a non-UTF-8 string to UTF-8.
     *
     * @param string $s The non-UTF-8 string to convert
     *
     * @return string The string converted to UTF-8
     */
    protected function utf8Encode($s)
    {
        if ('mbstring' === $this->charsetConverter) {
            return mb_convert_encoding($s, 'UTF-8', mb_check_encoding($s, $this->charset) ? $this->charset : '8bit');
        }
        if ('iconv' === $this->charsetConverter) {
            $valid = true;
            set_error_handler(function () use (&$valid) { $valid = false; });
            $c = iconv($this->charset, 'UTF-8', $s);
            restore_error_handler();
            if ($valid) {
                return $c;
            }
        }

        $s .= $s;
        $len = strlen($s);

        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80":
                    $s[$j] = $s[$i];
                    break;

                case $s[$i] < "\xC0":
                    $s[$j] = "\xC2";
                    $s[++$j] = $s[$i];
                    break;

                default:
                    $s[$j] = "\xC3";
                    $s[++$j] = chr(ord($s[$i]) - 64);
                    break;
            }
        }

        return substr($s, 0, $j);
    }
}
