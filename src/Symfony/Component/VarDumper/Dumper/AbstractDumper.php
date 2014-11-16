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

    /**
     * @param callable|resource|string|null $output A line dumper callable, an opened stream or an output path, defaults to static::$defaultOutput.
     */
    public function __construct($output = null)
    {
        $this->decimalPoint = (string) 0.5;
        $this->decimalPoint = $this->decimalPoint[1];
        $this->setOutput($output ?: static::$defaultOutput);
        if (!$output && is_string(static::$defaultOutput)) {
            static::$defaultOutput = $this->outputStream;
        }
    }

    /**
     * Sets the output destination of the dumps.
     *
     * @param callable|resource|string $output A line dumper callable, an opened stream or an output path.
     *
     * @return callable|resource|string The previous output destination.
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
     * Sets the indentation pad string.
     *
     * @param string $pad A string the will be prepended to dumped lines, repeated by nesting level.
     *
     * @return string The indent pad.
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
     * @param Data                          $data   A Data object.
     * @param callable|resource|string|null $output A line dumper callable, an opened stream or an output path.
     */
    public function dump(Data $data, $output = null)
    {
        $exception = null;
        if ($output) {
            $prevOutput = $this->setOutput($output);
        }
        try {
            $data->dump($this);
            $this->dumpLine(-1);
        } catch (\Exception $exception) {
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
     * @param int $depth The recursive depth in the dumped structure for the line being dumped.
     */
    protected function dumpLine($depth)
    {
        call_user_func($this->lineDumper, $this->line, $depth, $this->indentPad);
        $this->line = '';
    }

    /**
     * Generic line dumper callback.
     *
     * @param string $line  The line to write.
     * @param int    $depth The recursive depth in the dumped structure.
     */
    protected function echoLine($line, $depth, $indentPad)
    {
        if (-1 !== $depth) {
            fwrite($this->outputStream, str_repeat($indentPad, $depth).$line."\n");
        }
    }
}
