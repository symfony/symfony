<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Server\Connection;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final since Symfony 4.3
 */
class DumpDataCollector extends DataCollector implements DataDumperInterface
{
    private $stopwatch;
    private $fileLinkFormat;
    private $dataCount = 0;
    private $isCollected = true;
    private $clonesCount = 0;
    private $clonesIndex = 0;
    private $rootRefs;
    private $charset;
    private $requestStack;
    private $dumper;
    private $sourceContextProvider;

    /**
     * @param DataDumperInterface|Connection|null $dumper
     */
    public function __construct(Stopwatch $stopwatch = null, $fileLinkFormat = null, string $charset = null, RequestStack $requestStack = null, $dumper = null)
    {
        $this->stopwatch = $stopwatch;
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        $this->charset = $charset ?: ini_get('php.output_encoding') ?: ini_get('default_charset') ?: 'UTF-8';
        $this->requestStack = $requestStack;
        $this->dumper = $dumper;

        // All clones share these properties by reference:
        $this->rootRefs = [
            &$this->data,
            &$this->dataCount,
            &$this->isCollected,
            &$this->clonesCount,
        ];

        $this->sourceContextProvider = $dumper instanceof Connection && isset($dumper->getContextProviders()['source']) ? $dumper->getContextProviders()['source'] : new SourceContextProvider($this->charset);
    }

    public function __clone()
    {
        $this->clonesIndex = ++$this->clonesCount;
    }

    public function dump(Data $data)
    {
        if ($this->stopwatch) {
            $this->stopwatch->start('dump');
        }

        list('name' => $name, 'file' => $file, 'line' => $line, 'file_excerpt' => $fileExcerpt) = $this->sourceContextProvider->getContext();

        if ($this->dumper instanceof Connection) {
            if (!$this->dumper->write($data)) {
                $this->isCollected = false;
            }
        } elseif ($this->dumper) {
            $this->doDump($this->dumper, $data, $name, $file, $line);
        } else {
            $this->isCollected = false;
        }

        if (!$this->dataCount) {
            $this->data = [];
        }
        $this->data[] = compact('data', 'name', 'file', 'line', 'fileExcerpt');
        ++$this->dataCount;

        if ($this->stopwatch) {
            $this->stopwatch->stop('dump');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param \Throwable|null $exception
     */
    public function collect(Request $request, Response $response/*, \Throwable $exception = null*/)
    {
        if (!$this->dataCount) {
            $this->data = [];
        }

        // Sub-requests and programmatic calls stay in the collected profile.
        if ($this->dumper || ($this->requestStack && $this->requestStack->getMasterRequest() !== $request) || $request->isXmlHttpRequest() || $request->headers->has('Origin')) {
            return;
        }

        // In all other conditions that remove the web debug toolbar, dumps are written on the output.
        if (!$this->requestStack
            || !$response->headers->has('X-Debug-Token')
            || $response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || false === strripos($response->getContent(), '</body>')
        ) {
            if ($response->headers->has('Content-Type') && false !== strpos($response->headers->get('Content-Type'), 'html')) {
                $dumper = new HtmlDumper('php://output', $this->charset);
                $dumper->setDisplayOptions(['fileLinkFormat' => $this->fileLinkFormat]);
            } else {
                $dumper = new CliDumper('php://output', $this->charset);
                if (method_exists($dumper, 'setDisplayOptions')) {
                    $dumper->setDisplayOptions(['fileLinkFormat' => $this->fileLinkFormat]);
                }
            }

            foreach ($this->data as $dump) {
                $this->doDump($dumper, $dump['data'], $dump['name'], $dump['file'], $dump['line']);
            }
        }
    }

    public function reset()
    {
        if ($this->stopwatch) {
            $this->stopwatch->reset();
        }
        $this->data = [];
        $this->dataCount = 0;
        $this->isCollected = true;
        $this->clonesCount = 0;
        $this->clonesIndex = 0;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        if (!$this->dataCount) {
            $this->data = [];
        }

        if ($this->clonesCount !== $this->clonesIndex) {
            return [];
        }

        $this->data[] = $this->fileLinkFormat;
        $this->data[] = $this->charset;
        $this->dataCount = 0;
        $this->isCollected = true;

        return parent::__sleep();
    }

    /**
     * @internal
     */
    public function __wakeup()
    {
        parent::__wakeup();

        $charset = array_pop($this->data);
        $fileLinkFormat = array_pop($this->data);
        $this->dataCount = \count($this->data);

        self::__construct($this->stopwatch, $fileLinkFormat, $charset);
    }

    public function getDumpsCount()
    {
        return $this->dataCount;
    }

    public function getDumps($format, $maxDepthLimit = -1, $maxItemsPerDepth = -1)
    {
        $data = fopen('php://memory', 'r+b');

        if ('html' === $format) {
            $dumper = new HtmlDumper($data, $this->charset);
            $dumper->setDisplayOptions(['fileLinkFormat' => $this->fileLinkFormat]);
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid dump format: %s', $format));
        }
        $dumps = [];

        if (!$this->dataCount) {
            return $this->data = [];
        }

        foreach ($this->data as $dump) {
            $dumper->dump($dump['data']->withMaxDepth($maxDepthLimit)->withMaxItemsPerDepth($maxItemsPerDepth));
            $dump['data'] = stream_get_contents($data, -1, 0);
            ftruncate($data, 0);
            rewind($data);
            $dumps[] = $dump;
        }

        return $dumps;
    }

    public function getName()
    {
        return 'dump';
    }

    public function __destruct()
    {
        if (0 === $this->clonesCount-- && !$this->isCollected && $this->dataCount) {
            $this->clonesCount = 0;
            $this->isCollected = true;

            $h = headers_list();
            $i = \count($h);
            array_unshift($h, 'Content-Type: '.ini_get('default_mimetype'));
            while (0 !== stripos($h[$i], 'Content-Type:')) {
                --$i;
            }

            if (isset($_SERVER['VAR_DUMPER_FORMAT'])) {
                $html = 'html' === $_SERVER['VAR_DUMPER_FORMAT'];
            } else {
                $html = !\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && stripos($h[$i], 'html');
            }

            if ($html) {
                $dumper = new HtmlDumper('php://output', $this->charset);
                $dumper->setDisplayOptions(['fileLinkFormat' => $this->fileLinkFormat]);
            } else {
                $dumper = new CliDumper('php://output', $this->charset);
                if (method_exists($dumper, 'setDisplayOptions')) {
                    $dumper->setDisplayOptions(['fileLinkFormat' => $this->fileLinkFormat]);
                }
            }

            foreach ($this->data as $i => $dump) {
                $this->data[$i] = null;
                $this->doDump($dumper, $dump['data'], $dump['name'], $dump['file'], $dump['line']);
            }

            $this->data = [];
            $this->dataCount = 0;
        }
    }

    private function doDump(DataDumperInterface $dumper, $data, string $name, string $file, int $line)
    {
        if ($dumper instanceof CliDumper) {
            $contextDumper = function ($name, $file, $line, $fmt) {
                if ($this instanceof HtmlDumper) {
                    if ($file) {
                        $s = $this->style('meta', '%s');
                        $f = strip_tags($this->style('', $file));
                        $name = strip_tags($this->style('', $name));
                        if ($fmt && $link = \is_string($fmt) ? strtr($fmt, ['%f' => $file, '%l' => $line]) : $fmt->format($file, $line)) {
                            $name = sprintf('<a href="%s" title="%s">'.$s.'</a>', strip_tags($this->style('', $link)), $f, $name);
                        } else {
                            $name = sprintf('<abbr title="%s">'.$s.'</abbr>', $f, $name);
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
            $contextDumper($name, $file, $line, $this->fileLinkFormat);
        } else {
            $cloner = new VarCloner();
            $dumper->dump($cloner->cloneVar($name.' on line '.$line.':'));
        }
        $dumper->dump($data);
    }
}
