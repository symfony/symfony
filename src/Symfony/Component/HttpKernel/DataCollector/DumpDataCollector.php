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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\JsonDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpDataCollector extends DataCollector implements DataDumperInterface
{
    private $stopwatch;
    private $isCollected = true;
    private $clonesRoot;
    private $clonesCount = 0;

    public function __construct(Stopwatch $stopwatch = null)
    {
        $this->stopwatch = $stopwatch;
        $this->clonesRoot = $this;
    }

    public function __clone()
    {
        $this->data = array();
        $this->clonesRoot->clonesCount++;
    }

    public function dump(Data $data)
    {
        if ($this->stopwatch) {
           $this->stopwatch->start('dump');
        }
        if ($this->clonesRoot->isCollected) {
            $this->clonesRoot->isCollected = false;
            register_shutdown_function(array($this->clonesRoot, 'flushDumps'));
        }

        $trace = PHP_VERSION_ID >= 50306 ? DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS : true;
        if (PHP_VERSION_ID >= 50400) {
            $trace = debug_backtrace($trace, 6);
        } else {
            $trace = debug_backtrace($trace);
        }

        $file = $trace[0]['file'];
        $line = $trace[0]['line'];
        $name = false;
        $fileExcerpt = false;

        for ($i = 1; $i < 6; ++$i) {
            if (isset($trace[$i]['class'], $trace[$i]['function'])
                && 'dump' === $trace[$i]['function']
                && 'Symfony\Bundle\DebugBundle\DebugBundle' === $trace[$i]['class']
            ) {
                $file = $trace[$i]['file'];
                $line = $trace[$i]['line'];

                while (++$i < 6) {
                    if (isset($trace[$i]['function']) && empty($trace[$i]['class'])) {
                        $file = $trace[$i]['file'];
                        $line = $trace[$i]['line'];
                        break;
                    } elseif (isset($trace[$i]['object']) && $trace[$i]['object'] instanceof \Twig_Template) {
                        $info = $trace[$i]['object'];
                        $name = $info->getTemplateName();
                        $src = $info->getEnvironment()->getLoader()->getSource($name);
                        $info = $info->getDebugInfo();
                        if (isset($info[$trace[$i-1]['line']])) {
                            $file = false;
                            $line = $info[$trace[$i-1]['line']];
                            $src = explode("\n", $src);
                            $fileExcerpt = array();

                            for ($i = max($line - 3, 1), $max = min($line + 3, count($src)); $i <= $max; ++$i) {
                                $fileExcerpt[] = '<li'.($i === $line ? ' class="selected"' : '').'><code>'.htmlspecialchars($src[$i - 1]).'</code></li>';
                            }

                            $fileExcerpt = '<ol start="'.max($line - 3, 1).'">'.implode("\n", $fileExcerpt).'</ol>';
                        }
                        break;
                    }
                }
                break;
            }
        }

        if (false === $name) {
            $name = strtr($file, '\\', '/');
            $name = substr($file, strrpos($file, '/') + 1);
        }

        $this->clonesRoot->data[] = compact('data', 'name', 'file', 'line', 'fileExcerpt');

        if ($this->stopwatch) {
            $this->stopwatch->stop('dump');
        }
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
    }

    public function serialize()
    {
        $ser = serialize($this->clonesRoot->data);
        $this->clonesRoot->data = array();
        $this->clonesRoot->isCollected = true;

        return $ser;
    }

    public function unserialize($data)
    {
        parent::unserialize($data);

        $this->clonesRoot = $this;
    }

    public function getDumpsCount()
    {
        return count($this->clonesRoot->data);
    }

    public function getDumpsExcerpts()
    {
        $dumps = array();

        foreach ($this->data as $dump) {
            $data = $dump['data']->getRawData();
            unset($dump['data']);

            $data = $data[0][0];

            if (isset($data->val)) {
                $data = $data->val;
            }

            if (isset($data->bin)) {
                $data = 'b"'.$data->bin.'"';
            } elseif (isset($data->str)) {
                $data = '"'.$data->str.'"';
            } elseif (isset($data->count)) {
                $data = 'array('.$data->count.')';
            } elseif (isset($data->class)) {
                $data = $data->class.'{...}';
            } elseif (isset($data->res)) {
                $data = 'resource:'.$data->res.'{...}';
            } elseif (is_array($data)) {
                $data = 'array()';
            } elseif (null === $data) {
                $data = 'null';
            } elseif (false === $data) {
                $data = 'false';
            } elseif (INF === $data) {
                $data = 'INF';
            } elseif (-INF === $data) {
                $data = '-INF';
            } elseif (NAN === $data) {
                $data = 'NAN';
            } elseif (true === $data) {
                $data = 'true';
            }

            $dump['dataExcerpt'] = $data;
            $dumps[] = $dump;
        }

        return $dumps;
    }

    public function getDumps($getData = false)
    {
        if ($getData) {
            $dumper = new JsonDumper();
        }
        $dumps = array();

        foreach ($this->clonesRoot->data as $dump) {
            $json = '';
            if ($getData) {
                $dumper->dump($dump['data'], function ($line) use (&$json) {$json .= $line;});
            }
            $dump['data'] = $json;
            $dumps[] = $dump;
        }

        return $dumps;
    }

    public function getName()
    {
        return 'dump';
    }

    public function flushDumps()
    {
        if (0 === $this->clonesRoot->clonesCount-- && !$this->clonesRoot->isCollected && $this->clonesRoot->data) {
            $this->clonesRoot->clonesCount = 0;
            $this->clonesRoot->isCollected = true;

            $h = headers_list();
            $i = count($h);
            array_unshift($h, 'Content-Type: ' . ini_get('default_mimetype'));
            while (0 !== stripos($h[$i], 'Content-Type:')) {
                --$i;
            }

            if (stripos($h[$i], 'html')) {
                echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
                $dumper = new HtmlDumper();
            } else {
                $dumper = new CliDumper();
                $dumper->setColors(false);
            }

            foreach ($this->clonesRoot->data as $i => $dump) {
                $this->clonesRoot->data[$i] = null;

                if ($dumper instanceof HtmlDumper) {
                    $dump['name'] = htmlspecialchars($dump['name'], ENT_QUOTES, 'UTF-8');
                    $dump['file'] = htmlspecialchars($dump['file'], ENT_QUOTES, 'UTF-8');
                    if ('' !== $dump['file']) {
                        $dump['name'] = "<abbr title=\"{$dump['file']}\">{$dump['name']}</abbr>";
                    }
                    echo "\n<br><span class=\"sf-dump-meta\">in {$dump['name']} on line {$dump['line']}:</span>";
                } else {
                    echo "\nin {$dump['name']} on line {$dump['line']}:\n\n";
                }
                $dumper->dump($dump['data']);
            }

            $this->clonesRoot->data = array();
        }
    }
}
