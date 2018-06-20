<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\VarDumper;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * Used as fallback in the ServerDumper, it delays output to the end of the process for web requests.
 * It replicates the DumpDataCollector behavior to ensure dumps are written to the output
 * whenever the dump server isn't up and the debug toolbar not available.
 * In cli, output is not delayed and the provided CliDumper instance is used instead.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @internal
 */
final class DebugDumper implements DataDumperInterface, EventSubscriberInterface
{
    private $charset;
    private $fileLinkFormat;
    private $flags;
    private $cliDumper;
    private $sourceContextProvider;

    /** @var array[] */
    private $data = array();

    public function __construct(CliDumper $cliDumper, SourceContextProvider $sourceContextProvider, int $flags = 0)
    {
        $this->cliDumper = $cliDumper;
        $this->sourceContextProvider = $sourceContextProvider;
        $this->flags = $flags;
        $this->charset = $this->sourceContextProvider->getCharset();
        $this->fileLinkFormat = $this->sourceContextProvider->getFileLinkFormatter();
    }

    public function dump(Data $data)
    {
        list('name' => $name, 'file' => $file, 'line' => $line) = $this->sourceContextProvider->getContext();
        // only delay web requests
        if (\PHP_SAPI !== 'cli' && \PHP_SAPI !== 'phpdbg') {
            $this->data[] = array($data, $name, $file, $line);

            return;
        }

        $this->doDump($this->cliDumper, $data, $name, $file, $line);
    }

    private function doDump(DataDumperInterface $dumper, Data $data, string $name, string $file, int $line)
    {
        if ($dumper instanceof CliDumper) {
            $contextDumper = function (string $name, string $file, int $line, $fmt) {
                if ($this instanceof HtmlDumper) {
                    if ($file) {
                        $s = $this->style('meta', '%s');
                        $f = strip_tags($this->style('', $file));
                        $name = strip_tags($this->style('', $name));
                        if ($fmt && $link = is_string($fmt) ? strtr($fmt, array('%f' => $file, '%l' => $line)) : $fmt->format($file, $line)) {
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

    public function __destruct()
    {
        if ($this->data) {
            $h = headers_list();
            $i = \count($h);
            array_unshift($h, 'Content-Type: '.ini_get('default_mimetype'));
            while (0 !== stripos($h[$i], 'Content-Type:')) {
                --$i;
            }

            if (\PHP_SAPI !== 'cli' && \PHP_SAPI !== 'phpdbg' && stripos($h[$i], 'html')) {
                $dumper = new HtmlDumper('php://output', $this->charset);
                $dumper->setDisplayOptions(array('fileLinkFormat' => $this->fileLinkFormat));
            } else {
                $dumper = new CliDumper('php://output', $this->charset, $this->flags);
            }

            foreach ($this->data as list($data, $name, $file, $line)) {
                $this->doDump($dumper, $data, $name, $file, $line);
            }

            $this->data = array();
        }
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // In all conditions that remove the web debug toolbar, dumps must be written on the output.
        if (
            !$response->headers->has('X-Debug-Token')
            || $response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || false === strripos($response->getContent(), '</body>')
        ) {
            return;
        }

        // Otherwise, empty data and ignore.
        $this->data = array();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -100),
        );
    }
}
