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

use Symfony\Component\VarDumper\Caster\TraceStub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Dump
{
    // XXX dump to several destinations at one
    const AS_HTML = 'html';
    const AS_CLI = 'cli';
    const AS_TEXT = 'text';
    const AS_ENV = 'env';

    private $vars;
    private $dumpOnDestruct;
    private $to = null;
    private $format = 'env';
    private $die = false;
    private $title = true;
    private $trace = false;
    private $maxItems = 2500;
    private $maxDepth = -1;
    private $maxItemsPerDepth = -1;
    private $maxStringLength = -1;
    private $maxStringWidth = -1;
    private $collapseDepth = -1;
    private $collapseLength = -1;
    private $useRefHandles = true;
    private $casters = array();
    private $replaceCasters = false;
    private $filter = 0;
    private $lightArray = false;
    private $stringLength = false;
    private $charset = false;

    public function __construct(array $vars = array(), $dumpOnDestruct = true)
    {
        $this->vars = $vars;
        $this->dumpOnDestruct = $dumpOnDestruct;
    }

    public function __destruct()
    {
        if ($this->dumpOnDestruct) {
            $this->dump();
        }
    }

    public function dump()
    {
        $this->dumpOnDestruct = false;

        if (!$this->vars) {
            $this->trace = false;
            $this->lightArray = true;
            $this->vars = array(
                array(new TraceStub($this->getTrace())),
            );
        }
        if ($this->replaceCasters) {
            $cloner = new VarCloner($this->casters);
        } else {
            $cloner = new VarCloner();
            $cloner->addCasters($this->casters);
        }
        $cloner->setMaxItems($this->maxItems);
        $cloner->setMaxString($this->maxStringLength);
        $displayOptions = array(
            'maxDepth' => $this->collapseDepth > 0 ? $this->collapseDepth : 0,
            'maxDepth' => $this->collapseLength,
        );

        $flags = $this->lightArray ? CliDumper::DUMP_LIGHT_ARRAY : 0;
        $flags |= $this->stringLength ? CliDumper::DUMP_STRING_LENGTH : 0;
        $dumper = $this->getDumper($this->getFormat($this->format), $this->output, $this->charset, $flags, $displayOptions);
        $dumper->setMaxStringWidth($this->maxStringWidth);

        foreach ($this->vars as $v) {
            $dumper->dump($cloner->cloneVar($v, $this->filters)
                ->withMaxDepth($this->maxDepth)
                ->withMaxItemsPerDepth($this->maxItemsPerDepth)
                ->withRefHandles($this->useRefHandles)
            );
        }

        if ($this->die) {
            exit(1);
        }
    }

    protected function getFormat($format)
    {
        foreach (array($format, getenv('DUMP_FORMAT')) as $format) {
            $format = strtolower($format);
            if (self::AS_HTML === $format || self::AS_CLI === $format || self::AS_TEXT === $format) {
                return $format;
            }
        }

        return 'cli' === PHP_SAPI ? self::AS_CLI : self::AS_HTML;
    }

    protected function getDumper($format, $output, $charset, $flags, $displayOptions)
    {
        if (self::AS_HTML === $format) {
            $dumper = new HtmlDumper($output, $charset, $flags);
            $dumper->setDisplayOptions($displayOptions);
        } else {
            $dumper = new CliDumper($output, $charset, $flags);

            if (self::AS_TEXT === $format) {
                $dumper->setColors(false);
            }
        }

        return $dumper;
    }

    private function dumpTitle($dumper, \Exception $xTrace)
    {
        if (!$dumper instanceof CliDumper) {
            $cloner = new VarCloner();
            $dumper->dump($cloner->cloneVar($name.' on line '.$line.':'));

            return;
        }

        $contextDumper = function ($name, $file, $line, $fileLinkFormat = false) {
            if ($this instanceof HtmlDumper) {
                if ('' !== $file) {
                    $s = $this->style('meta', '%s');
                    $name = strip_tags($this->style('', $name));
                    $file = strip_tags($this->style('', $file));
                    if ($fileLinkFormat) {
                        $link = strtr(strip_tags($this->style('', $fileLinkFormat)), array('%f' => $file, '%l' => (int) $line));
                        $name = sprintf('<a href="%s" title="%s">'.$s.'</a>', $link, $file, $name);
                    } else {
                        $name = sprintf('<abbr title="%s">'.$s.'</abbr>', $file, $name);
                    }
                } else {
                    $name = $this->style('meta', $name);
                }
                $this->line = $name.' on line '.$this->style('meta', $line).':';
            } else {
                $this->line = $this->style('meta', $name).' on line '.$this->style('meta', $line).':';
            }
            $this->dumpLine(0);
        };
        $contextDumper = $contextDumper->bindTo($dumper, $dumper);
        $contextDumper($name, $file, $line);
    }

    private function getTrace()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

        do {
            array_shift($trace);
        } while (isset($trace[0]['class']) && __CLASS__ === $trace[0]['class']);

        return $trace;
    }

    /**
     * Sets the output destination of the dump.
     *
     * @param callable|resource|string $output A line callable, an opened stream or an output path.
     *
     * @return self
     */
    public function to($output)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->output = $output;

        return $dump;
    }

    /**
     * Sets the output format of the dump as one of the self::AS_* constants, the default being to check the `DUMP_FORMAT` env var then the PHP SAPI.
     *
     * @return self
     */
    public function format($format)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->format = (string) $format;

        return $dump;
    }

    /**
     * Dies after dumping.
     *
     * @return self
     */
    public function thenDie($die = true)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->die = (bool) $die;

        return $dump;
    }

    /**
     * Enables/disables dumping the title of the dump.
     *
     * @return self
     */
    public function withTitle($title)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->title = (bool) $title;

        return $dump;
    }

    /**
     * Enables/disables dumping the stack trace after dumping.
     *
     * @return self
     */
    public function withTrace($trace = true)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->trace = (bool) $trace;

        return $dump;
    }

    /**
     * Limits the number of items past the first level.
     *
     * @return self
     */
    public function withMaxItems($items)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->maxItems = (int) $items;

        return $dump;
    }

    /**
     * Limits the depth of the dump.
     *
     * @return self
     */
    public function withMaxDepth($depth)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->maxDepth = (int) $depth;

        return $dump;
    }

    /**
     * Limits the number of items per depth level.
     *
     * @return self
     */
    public function withMaxItemsPerDepth($max)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->maxItemsPerDepth = (int) $max;

        return $dump;
    }

    /**
     * Limits the number of characters for dumped strings.
     *
     * @return self
     */
    public function withMaxLength($length)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->maxStringLength = (int) $length;

        return $dump;
    }

    /**
     * Limits the number of characters per line for dumped strings.
     *
     * @return self
     */
    public function withMaxStringWidth($width)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->maxStringWidth = (int) $width;

        return $dump;
    }

    /**
     * For HTML dumps: number of levels to collapse before collapsing.
     *
     * @return self
     */
    public function withCollapseDepth($depth)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->collapseDepth = (int) $depth;

        return $dump;
    }

    /**
     * For HTML dumps: number of string characters to collapse before collapsing.
     *
     * @return self
     */
    public function withCollapseLength($length)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->collapseLength = (int) $length;

        return $dump;
    }

    /**
     * Enables/disables objects' global identifiers dumping.
     *
     * @return self
     */
    public function withRefHandles($handles)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->useRefHandles = (bool) $handles;

        return $dump;
    }

    /**
     * Adds or replaces casters for resources and objects.
     *
     * @return self
     */
    public function withCasters(array $casters, $replace = false)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->casters = $casters;
        $dump->replaceCasters = (bool) $replace;

        return $dump;
    }

    /**
     * A bit field of Caster::EXCLUDE_* constants.
     *
     * @return self
     */
    public function withFilter($filter)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->filter = (int) $filter;

        return $dump;
    }

    /**
     * Whether to dump array length and keys of numerically indexed arrays or not.
     *
     * @return self
     */
    public function withLightArray($light = true)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->lightArray = (int) $light;

        return $dump;
    }

    /**
     * Whether to dump string length.
     *
     * @return self
     */
    public function withStringLength($length = true)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->stringLength = (bool) $length;

        return $dump;
    }

    /**
     * Sets the character encoding to use for non-UTF8 strings.
     *
     * @return self
     */
    public function withCharset($charset)
    {
        $dump = clone $this;
        $this->dumpOnDestruct = false;
        $dump->charset = (string) $charset;

        return $dump;
    }
}
