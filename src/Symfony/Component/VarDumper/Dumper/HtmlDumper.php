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

/**
 * HtmlDumper dumps variables as HTML.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HtmlDumper extends CliDumper
{
    public static $defaultOutputStream = 'php://output';

    protected $dumpHeader;
    protected $dumpPrefix = '<pre class=sf-dump style=white-space:pre>';
    protected $dumpSuffix = '</pre>';
    protected $colors = true;
    protected $headerIsDumped = false;
    protected $lastDepth = -1;
    protected $styles = array(
        'num'       => 'font-weight:bold;color:#0087FF',
        'const'     => 'font-weight:bold;color:#0087FF',
        'str'       => 'font-weight:bold;color:#00D7FF',
        'cchr'      => 'font-style: italic',
        'note'      => 'color:#D7AF00',
        'ref'       => 'color:#444444',
        'public'    => 'color:#008700',
        'protected' => 'color:#D75F00',
        'private'   => 'color:#D70000',
        'meta'      => 'color:#005FFF',
    );

    /**
     * {@inheritdoc}
     */
    public function setLineDumper($callback)
    {
        $this->headerIsDumped = false;

        return parent::setLineDumper($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function setStyles(array $styles)
    {
        $this->headerIsDumped = false;
        $this->styles = $styles + $this->styles;
    }

    /**
     * Sets an HTML header the will be dumped once in the output stream.
     *
     * @param string $header An HTML string.
     */
    public function setDumpHeader($header)
    {
        $this->dumpHeader = $header;
    }

    /**
     * Sets an HTML prefix and suffix that will encapse every single dump.
     *
     * @param string $prefix The prepended HTML string.
     * @param string $suffix The appended HTML string.
     */
    public function setDumpBoudaries($prefix, $suffix)
    {
        $this->dumpPrefix = $prefix;
        $this->dumpSuffix = $suffix;
    }

    /**
     * Dumps the HTML header.
     */
    protected function dumpHeader()
    {
        $this->headerIsDumped = true;
        $line = $this->line;

        $p = 'sf-dump';
        $this->line = '<!DOCTYPE html><style>';
        parent::dumpLine(0);
        $this->line .= "a.$p-ref {{$this->styles['ref']}}";
        parent::dumpLine(0);

        foreach ($this->styles as $class => $style) {
            $this->line .= "span.$p-$class {{$style}}";
            parent::dumpLine(0);
        }

        $this->line .= '</style>';
        parent::dumpLine(0);
        $this->line .= $this->dumpHeader;
        parent::dumpLine(0);

        $this->line = $line;
    }

    /**
     * {@inheritdoc}
     */
    protected function style($style, $val)
    {
        if ('' === $val) {
            return '';
        }

        if ('ref' === $style) {
            $ref = substr($val, 1);
            if ('#' === $val[0]) {
                return "<a class=sf-dump-ref name=\"sf-dump-ref$ref\">$val</a>";
            } else {
                return "<a class=sf-dump-ref href=\"#sf-dump-ref$ref\">$val</a>";
            }
        }

        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

        if ('str' === $style || 'meta' === $style || 'public' === $style) {
            foreach (static::$controlChars as $c) {
                if (false !== strpos($val, $c)) {
                    $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                    $val = str_replace($c, "<span class=sf-dump-cchr>$r</span>", $val);
                }
            }
        }

        return "<span class=sf-dump-$style>$val</span>";
    }

    /**
     * {@inheritdoc}
     */
    protected function dumpLine($depth)
    {
        if (!$this->headerIsDumped) {
            $this->dumpHeader();
        }

        switch ($this->lastDepth - $depth) {
            case +1: $this->line = '</span>'.$this->line; break;
            case -1: $this->line = "<span class=sf-dump-$depth>$this->line"; break;
        }

        if (-1 === $this->lastDepth) {
            $this->line = $this->dumpPrefix.$this->line;
        }

        if (false === $depth) {
            $this->lastDepth = -1;
            $this->line .= $this->dumpSuffix;
            parent::dumpLine(0);
        } else {
            $this->lastDepth = $depth;
        }

        parent::dumpLine($depth);
    }
}
