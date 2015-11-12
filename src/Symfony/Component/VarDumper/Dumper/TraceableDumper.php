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

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * TraceableDumper.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TraceableDumper implements DataDumperInterface
{
    private $dumper;
    private $stopwatch;
    private $fileLinkFormat;
    private $data = array();
    private $dataCount = 0;
    private $isCollected = true;
    private $twigLoader = null;

    /**
     * Constructor.
     *
     * @param DataDumperInterface   $dumper
     * @param Stopwatch             $stopwatch
     * @param string|null           $fileLinkFormat
     * @param string|null           $charset
     */
    public function __construct(DataDumperInterface $dumper = null, Stopwatch $stopwatch = null, $fileLinkFormat = null,
                                $charset = null, \Twig_LoaderInterface $twigLoader = null)
    {
        $this->stopwatch = $stopwatch;
        $this->dumper = $dumper;
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        $this->charset = $charset ?: ini_get('php.output_encoding') ?: ini_get('default_charset') ?: 'UTF-8';
        $this->twigLoader = $twigLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function dump(Data $data)
    {
        if ($this->stopwatch) {
            $this->stopwatch->start('dump');
        }

        if ($this->isCollected) {
            $this->isCollected = false;
        }

        $trace = DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS;
        if (PHP_VERSION_ID >= 50400) {
            $trace = debug_backtrace($trace, 7);
        } else {
            $trace = debug_backtrace($trace);
        }

        $file = isset($trace[0]['file']) ? $trace[0]['file'] : null;
        $line = isset($trace[0]['line']) ? $trace[0]['line'] : null;
        $name = false;
        $fileExcerpt = false;

        for ($i = 1; $i < 7; ++$i) {
            if (isset($trace[$i]['class'], $trace[$i]['function'])
                && 'dump' === $trace[$i]['function']
                && 'Symfony\Component\VarDumper\VarDumper' === $trace[$i]['class']
            ) {
                $file = $trace[$i]['file'];
                $line = $trace[$i]['line'];

                while (++$i < 7) {
                    if (isset($trace[$i]['function'], $trace[$i]['file']) && empty($trace[$i]['class']) && 0 !== strpos($trace[$i]['function'], 'call_user_func')) {
                        $file = $trace[$i]['file'];
                        $line = $trace[$i]['line'];
                        if (isset($trace[++$i]) && isset($trace[$i]['object']) && $trace[$i]['object'] instanceof \Twig_Template) {
                            $info = $trace[$i]['object'];
                            $name = $info->getTemplateName();
                            $info = $info->getDebugInfo();
                            $line = $trace[$i - 1]['line'];
                            if ( !isset($info[$line]) ) {
                                $line--;
                            }

                            if (null !== $this->twigLoader && isset($info[$line])) {

                                $src = $this->twigLoader->getSource($name);
                                $file = false;
                                $line = $info[$line];
                                $src = explode("\n", $src);
                                $fileExcerpt = array();

                                for ($i = max($line - 3, 1), $max = min($line + 3, count($src)); $i <= $max; ++$i) {
                                    $fileExcerpt[] = '<li'.($i === $line ? ' class="selected"' : '').'><code>'.$this->htmlEncode($src[$i - 1]).'</code></li>';
                                }

                                $fileExcerpt = '<ol start="'.max($line - 3, 1).'">'.implode("\n", $fileExcerpt).'</ol>';
                            }
                        }
                        break;
                    }
                }
                break;
            }
        }

        if (false === $name) {
            $name = strtr($file, '\\', '/');
            $name = substr($name, strrpos($name, '/') + 1);
        }

        if ($this->dumper) {
            $this->doDump($data, $name, $file, $line);
        }

        $this->data[] = compact('data', 'name', 'file', 'line', 'fileExcerpt');
        ++$this->dataCount;

        if ($this->stopwatch) {
            $this->stopwatch->stop('dump');
        }
    }

    /**
     * Trigger force writing output.
     *
     * @param string|null $format
     */
    public function forceOutput($format = null)
    {
        $currentDumper = $this->dumper;
        if ('html' == $format) {
            $this->dumper = new HtmlDumper('php://output', $this->charset);
        } else {
            $this->dumper = new CliDumper('php://output', $this->charset);
        }
        foreach ($this->getData() as $dump) {
            $this->doDump($dump['data'], $dump['name'], $dump['file'], $dump['line']);
        }
        $this->dumper = $currentDumper;
    }

    /**
     * Returns the collection of dumps.
     *
     * @return array
     */
    public function getData()
    {
        $data = $this->data;
        $this->isCollected = true;
        $this->data = array();
        $this->dataCount = 0;
        return $data;
    }

    /**
     * Returns the Charset.
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        if (!$this->isCollected && $this->data) {
            $this->isCollected = true;

            $h = headers_list();
            $i = count($h);
            array_unshift($h, 'Content-Type: '.ini_get('default_mimetype'));
            while (0 !== stripos($h[$i], 'Content-Type:')) {
                --$i;
            }

            if ('cli' !== PHP_SAPI && stripos($h[$i], 'html')) {
                $this->dumper = new HtmlDumper('php://output', $this->charset);
            } else {
                $this->dumper = new CliDumper('php://output', $this->charset);
            }

            foreach ($this->data as $i => $dump) {
                $this->data[$i] = null;
                $this->doDump($dump['data'], $dump['name'], $dump['file'], $dump['line']);
            }

            $this->data = array();
            $this->dataCount = 0;
        }
    }

    private function doDump($data, $name, $file, $line)
    {
        if (PHP_VERSION_ID >= 50400 && $this->dumper instanceof CliDumper) {
            $contextDumper = function ($name, $file, $line, $fileLinkFormat) {
                if ($this instanceof HtmlDumper) {
                    if ('' !== $file) {
                        $s = $this->style('meta', '%s');
                        $name = strip_tags($this->style('', $name));
                        $file = strip_tags($this->style('', $file));
                        if ($fileLinkFormat) {
                            $link = strtr($fileLinkFormat, array('%f' => $file, '%l' => (int) $line));
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
            $contextDumper = $contextDumper->bindTo($this->dumper, $this->dumper);
            $contextDumper($name, $file, $line, $this->fileLinkFormat);
        } else {
            $cloner = new VarCloner();
            $this->dumper->dump($cloner->cloneVar($name.' on line '.$line.':'));
        }
        $this->dumper->dump($data);
    }

    private function htmlEncode($s)
    {
        $html = '';

        $dumper = new HtmlDumper(function ($line) use (&$html) {
            $html .= $line;
        }, $this->charset);
        $dumper->setDumpHeader('');
        $dumper->setDumpBoundaries('', '');

        $cloner = new VarCloner();
        $dumper->dump($cloner->cloneVar($s));

        return substr(strip_tags($html), 1, -1);
    }
}