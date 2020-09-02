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
    const DUMP_LIGHT_ARRAY = 1;
    const DUMP_STRING_LENGTH = 2;
    const DUMP_COMMA_SEPARATOR = 4;
    const DUMP_TRAILING_COMMA = 8;

    public static $defaultOutput = 'php://output';

    protected $line = '';
    protected $lineDumper;
    protected $outputStream;
    protected $decimalPoint; // This is locale dependent
    protected $indentPad = '  ';
    protected $flags;

    private $charset = '';

    /**
     * @param callable|resource|string|null $output  A line dumper callable, an opened stream or an output path, defaults to static::$defaultOutput
     * @param string|null                   $charset The default character encoding to use for non-UTF8 strings
     * @param int                           $flags   A bit field of static::DUMP_* constants to fine tune dumps representation
     */
    public function __construct($output = null, string $charset = null, int $flags = 0)
    {
        $this->flags = $flags;
        $this->setCharset($charset ?: ini_get('php.output_encoding') ?: ini_get('default_charset') ?: 'UTF-8');
        $this->decimalPoint = localeconv();
        $this->decimalPoint = $this->decimalPoint['decimal_point'];
        $this->setOutput($output ?: static::$defaultOutput);
        if (!$output && \is_string(static::$defaultOutput)) {
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

        if (\is_callable($output)) {
            $this->outputStream = null;
            $this->lineDumper = $output;
        } else {
            if (\is_string($output)) {
                $output = fopen($output, 'wb');
            }
            $this->outputStream = $output;
            $this->lineDumper = [$this, 'echoLine'];
        }

        return $prev;
    }

    /**
     * Sets the default character encoding to use for non-UTF8 strings.
     *
     * @return string The previous charset
     */
    public function setCharset(string $charset)
    {
        $prev = $this->charset;

        $charset = strtoupper($charset);
        $charset = null === $charset || 'UTF-8' === $charset || 'UTF8' === $charset ? 'CP1252' : $charset;

        $this->charset = $charset;

        return $prev;
    }

    /**
     * Sets the indentation pad string.
     *
     * @param string $pad A string that will be prepended to dumped lines, repeated by nesting level
     *
     * @return string The previous indent pad
     */
    public function setIndentPad(string $pad)
    {
        $prev = $this->indentPad;
        $this->indentPad = $pad;

        return $prev;
    }

    /**
     * Dumps a Data object.
     *
     * @param callable|resource|string|true|null $output A line dumper callable, an opened stream, an output path or true to return the dump
     *
     * @return string|null The dump as string when $output is true
     */
    public function dump(Data $data, $output = null)
    {
        $this->decimalPoint = localeconv();
        $this->decimalPoint = $this->decimalPoint['decimal_point'];

        if ($locale = $this->flags & (self::DUMP_COMMA_SEPARATOR | self::DUMP_TRAILING_COMMA) ? setlocale(\LC_NUMERIC, 0) : null) {
            setlocale(\LC_NUMERIC, 'C');
        }

        if ($returnDump = true === $output) {
            $output = fopen('php://memory', 'r+b');
        }
        if ($output) {
            $prevOutput = $this->setOutput($output);
        }
        try {
            $data->dump($this);
            $this->dumpLine(-1);

            if ($returnDump) {
                $result = stream_get_contents($output, -1, 0);
                fclose($output);

                return $result;
            }
        } finally {
            if ($output) {
                $this->setOutput($prevOutput);
            }
            if ($locale) {
                setlocale(\LC_NUMERIC, $locale);
            }
        }

        return null;
    }

    /**
     * Dumps the current line.
     *
     * @param int $depth The recursive depth in the dumped structure for the line being dumped,
     *                   or -1 to signal the end-of-dump to the line dumper callable
     */
    protected function dumpLine(int $depth)
    {
        ($this->lineDumper)($this->line, $depth, $this->indentPad);
        $this->line = '';
    }

    /**
     * Generic line dumper callback.
     */
    protected function echoLine(string $line, int $depth, string $indentPad)
    {
        if (-1 !== $depth) {
            fwrite($this->outputStream, str_repeat($indentPad, $depth).$line."\n");
        }
    }

    /**
     * Converts a non-UTF-8 string to UTF-8.
     *
     * @return string|null The string converted to UTF-8
     */
    protected function utf8Encode(?string $s)
    {
        if (null === $s || preg_match('//u', $s)) {
            return $s;
        }

        if (!\function_exists('iconv')) {
            throw new \RuntimeException('Unable to convert a non-UTF-8 string to UTF-8: required function iconv() does not exist. You should install ext-iconv or symfony/polyfill-iconv.');
        }

        if (false !== $c = @iconv($this->charset, 'UTF-8', $s)) {
            return $c;
        }
        if ('CP1252' !== $this->charset && false !== $c = @iconv('CP1252', 'UTF-8', $s)) {
            return $c;
        }

        return iconv('CP850', 'UTF-8', $s);
    }
}
