<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Debug\Exception\FlattenException;

if (!defined('ENT_SUBSTITUTE')) {
    define('ENT_SUBSTITUTE', 8);
}

/**
 * ExceptionHandler converts an exception to a Response object.
 *
 * It is mostly useful in debug mode to replace the default PHP/XDebug
 * output with something prettier and more useful.
 *
 * As this class is mainly used during Kernel boot, where nothing is yet
 * available, the Response content is always HTML.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    private $debug;
    private $charset;

    public function __construct($debug = true, $charset = 'UTF-8')
    {
        $this->debug = $debug;
        $this->charset = $charset;
    }

    /**
     * Registers the exception handler.
     *
     * @param bool    $debug
     *
     * @return ExceptionHandler The registered exception handler
     */
    public static function register($debug = true)
    {
        $handler = new static($debug);

        set_exception_handler(array($handler, 'handle'));

        return $handler;
    }

    /**
     * {@inheritdoc}
     *
     * Sends a response for the given Exception.
     *
     * If you have the Symfony HttpFoundation component installed,
     * this method will use it to create and send the response. If not,
     * it will fallback to plain PHP functions.
     *
     * @see sendPhpResponse
     * @see createResponse
     */
    public function handle(\Exception $exception)
    {
        if (class_exists('Symfony\Component\HttpFoundation\Response')) {
            $response = $this->createResponse($exception);
            $response->sendHeaders();
            $response->sendContent();
        } else {
            $this->sendPhpResponse($exception);
        }
    }

    /**
     * Sends the error associated with the given Exception as a plain PHP response.
     *
     * This method uses plain PHP functions like header() and echo to output
     * the response.
     *
     * @param \Exception|FlattenException $exception An \Exception instance
     */
    public function sendPhpResponse($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        if (!headers_sent()) {
            header(sprintf('HTTP/1.0 %s', $exception->getStatusCode()));
            foreach ($exception->getHeaders() as $name => $value) {
                header($name.': '.$value, false);
            }
        }

        echo $this->decorate($this->getContent($exception), $this->getStylesheet($exception));
    }

    /**
     * Creates the error Response associated with the given Exception.
     *
     * @param \Exception|FlattenException $exception An \Exception instance
     *
     * @return Response A Response instance
     */
    public function createResponse($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return new Response($this->decorate($this->getContent($exception), $this->getStylesheet($exception)), $exception->getStatusCode(), $exception->getHeaders());
    }

    /**
     * Gets the HTML content associated with the given exception.
     *
     * @param FlattenException $exception A FlattenException instance
     *
     * @return string The content as a string
     */
    public function getContent(FlattenException $exception)
    {
        switch ($exception->getStatusCode()) {
            case 404:
                $title = 'Sorry, the page you are looking for could not be found.';
                break;
            default:
                $title = 'Whoops, looks like something went wrong.';
        }

        $content = '';
        if ($this->debug) {
            try {
                $count = count($exception->getAllPrevious());
                $total = $count + 1;
                foreach ($exception->toArray() as $position => $e) {
                    $ind = $count - $position + 1;
                    $class = $this->abbrClass($e['class']);
                    $message = nl2br($e['message']);
                    $content .= sprintf(<<<EOF
                        <div class="block_exception clear_fix">
                            <h2><span>%d/%d</span> %s: %s</h2>
                        </div>
                        <div class="block">
                            <ol class="traces list_exception">

EOF
                        , $ind, $total, $class, $message);
                    foreach ($e['trace'] as $i => $trace) {
                        $content .= '       <li>';
                        if ($trace['function']) {
                            $content .= sprintf('at %s%s%s(%s)', $this->abbrClass($trace['class']), $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                        }
                        if (isset($trace['file']) && isset($trace['line'])) {
                            if ($linkFormat = ini_get('xdebug.file_link_format')) {
                                $link = str_replace(array('%f', '%l'), array($trace['file'], $trace['line']), $linkFormat);
                                $content .= sprintf(' in <a href="%s" title="Go to source">%s line %s</a>', $link, $trace['file'], $trace['line']);
                            } else {
                                $content .= sprintf(' in %s line %s', $trace['file'], $trace['line']);
                            }
                            $content .= strtr(<<<EOF
                                <a href="#" onclick="toggle('trace-{{ prefix }}-{{ i }}'); switchIcons('icon-{{ prefix }}-{{ i }}-open', 'icon-{{ prefix }}-{{ i }}-close'); return false;">
                                    <img class="toggle" id="icon-{{ prefix }}-{{ i }}-close" alt="-" src="data:image/gif;base64,R0lGODlhEgASAMQSANft94TG57Hb8GS44ez1+mC24IvK6ePx+Wa44dXs92+942e54o3L6W2844/M6dnu+P/+/l614P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAABIALAAAAAASABIAQAVCoCQBTBOd6Kk4gJhGBCTPxysJb44K0qD/ER/wlxjmisZkMqBEBW5NHrMZmVKvv9hMVsO+hE0EoNAstEYGxG9heIhCADs=" style="display: {{ display_plus }}" />
                                    <img class="toggle" id="icon-{{ prefix }}-{{ i }}-open" alt="+" src="data:image/gif;base64,R0lGODlhEgASAMQTANft99/v+Ga44bHb8ITG52S44dXs9+z1+uPx+YvK6WC24G+944/M6W28443L6dnu+Ge54v/+/l614P///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAABMALAAAAAASABIAQAVS4DQBTiOd6LkwgJgeUSzHSDoNaZ4PU6FLgYBA5/vFID/DbylRGiNIZu74I0h1hNsVxbNuUV4d9SsZM2EzWe1qThVzwWFOAFCQFa1RQq6DJB4iIQA7" style="display: {{ display_minus }}" />
                                </a>
                                <div id="trace-{{ prefix }}-{{ i }}" style="display: {{ display_trace }}" class="trace">
                                    {{ trace }}
                                </div>
EOF
                            , array(
                                '{{ prefix }}' => $position,
                                '{{ i }}' => $i,
                                '{{ display_plus }}' => 0 == $i ? 'inline' : 'none',
                                '{{ display_minus }}' => 0 == $i ? 'none' : 'inline',
                                '{{ display_trace }}' => 0 == $i ? 'block' : 'none',
                                '{{ trace }}' => $this->fileExcerpt($trace['file'], $trace['line']),
                            ));
                        }

                        $content .= "</li>\n";
                    }

                    $content .= "    </ol>\n</div>\n";
                }
            } catch (\Exception $e) {
                // something nasty happened and we cannot throw an exception anymore
                if ($this->debug) {
                    $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($exception), $exception->getMessage());
                } else {
                    $title = 'Whoops, looks like something went wrong.';
                }
            }
        }

        return <<<EOF
            <div id="sf-resetcontent" class="sf-reset">
                <h1>$title</h1>
                $content
            </div>
EOF;
    }

    /**
     * Gets the stylesheet associated with the given exception.
     *
     * @param FlattenException $exception A FlattenException instance
     *
     * @return string The stylesheet as a string
     */
    public function getStylesheet(FlattenException $exception)
    {
        return <<<EOF
            .sf-reset { font: 11px Verdana, Arial, sans-serif; color: #333 }
            .sf-reset .clear { clear:both; height:0; font-size:0; line-height:0; }
            .sf-reset .clear_fix:after { display:block; height:0; clear:both; visibility:hidden; }
            .sf-reset .clear_fix { display:inline-block; }
            .sf-reset * html .clear_fix { height:1%; }
            .sf-reset .clear_fix { display:block; }
            .sf-reset, .sf-reset .block { margin: auto }
            .sf-reset abbr { border-bottom: 1px dotted #000; cursor: help; }
            .sf-reset p { font-size:14px; line-height:20px; color:#868686; padding-bottom:20px }
            .sf-reset strong { font-weight:bold; }
            .sf-reset a { color:#6c6159; }
            .sf-reset a img { border:none; }
            .sf-reset a:hover { text-decoration:underline; }
            .sf-reset em { font-style:italic; }
            .sf-reset h1, .sf-reset h2 { font: 20px Georgia, "Times New Roman", Times, serif }
            .sf-reset h2 span { background-color: #fff; color: #333; padding: 6px; float: left; margin-right: 10px; }
            .sf-reset .block { background-color:#FFFFFF; padding:10px 28px; margin-bottom:20px;
                -webkit-border-bottom-right-radius: 16px;
                -webkit-border-bottom-left-radius: 16px;
                -moz-border-radius-bottomright: 16px;
                -moz-border-radius-bottomleft: 16px;
                border-bottom-right-radius: 16px;
                border-bottom-left-radius: 16px;
                border-bottom:1px solid #ccc;
                border-right:1px solid #ccc;
                border-left:1px solid #ccc;
            }
            .sf-reset .block_exception { background-color:#ddd; color: #333; padding:20px;
                -webkit-border-top-left-radius: 16px;
                -webkit-border-top-right-radius: 16px;
                -moz-border-radius-topleft: 16px;
                -moz-border-radius-topright: 16px;
                border-top-left-radius: 16px;
                border-top-right-radius: 16px;
                border-top:1px solid #ccc;
                border-right:1px solid #ccc;
                border-left:1px solid #ccc;
                overflow: hidden;
                word-wrap: break-word;
            }
            .sf-reset h1 { background-color:#FFFFFF; padding: 15px 28px; margin-bottom: 20px;
                -webkit-border-radius: 10px;
                -moz-border-radius: 10px;
                border-radius: 10px;
                border: 1px solid #ccc;
            }
            .sf-reset .traces { padding-bottom: 14px; }
            .sf-reset .traces li { font-size: 12px; color: #868686; padding: 5px 4px; list-style-type: decimal; margin-left: 20px; white-space: break-word; }
            .sf-reset .trace { border: 1px solid #D3D3D3; padding: 10px; overflow: auto; margin: 10px 0 20px; }
            .sf-reset ol { padding: 10px 0; }
            .sf-reset ol li { list-style: decimal; margin-left: 20px; padding: 2px; padding-bottom: 20px; }
            .sf-reset ol ol li { list-style-position: inside; margin-left: 0; white-space: nowrap; font-size: 12px; padding-bottom: 0; }
            .sf-reset li a { background:none; color:#868686; text-decoration:none; }
            .sf-reset li a:hover { background:none; color:#313131; text-decoration:underline; }
            .sf-reset li .selected { background-color: #ffd; }
EOF;
    }

    private function decorate($content, $css)
    {
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <meta name="robots" content="noindex,nofollow" />
        <style>
            /* Copyright (c) 2010, Yahoo! Inc. All rights reserved. Code licensed under the BSD License: http://developer.yahoo.com/yui/license.html */
            html{color:#000;background:#FFF;}body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,code,form,fieldset,legend,input,textarea,p,blockquote,th,td{margin:0;padding:0;}table{border-collapse:collapse;border-spacing:0;}fieldset,img{border:0;}address,caption,cite,code,dfn,em,strong,th,var{font-style:normal;font-weight:normal;}li{list-style:none;}caption,th{text-align:left;}h1,h2,h3,h4,h5,h6{font-size:100%;font-weight:normal;}q:before,q:after{content:'';}abbr,acronym{border:0;font-variant:normal;}sup{vertical-align:text-top;}sub{vertical-align:text-bottom;}input,textarea,select{font-family:inherit;font-size:inherit;font-weight:inherit;}input,textarea,select{*font-size:100%;}legend{color:#000;}

            html { background: #eee; padding: 10px }
            img { border: 0; }
            #sf-resetcontent { width:970px; margin:0 auto; }
            $css
        </style>
    </head>
    <body>
        $content

        <script type="text/javascript">//<![CDATA[
            function toggle(id, clazz)
            {
                var el = document.getElementById(id),
                    current = el.style.display,
                    i;

                if (clazz) {
                    var tags = document.getElementsByTagName('*');
                    for (i = tags.length - 1; i >= 0 ; i--) {
                        if (tags[i].className === clazz) {
                            tags[i].style.display = 'none';
                        }
                    }
                }

                el.style.display = current === 'none' ? 'block' : 'none';
            }

            function switchIcons(id1, id2)
            {
                var icon1, icon2, display1, display2;

                icon1 = document.getElementById(id1);
                icon2 = document.getElementById(id2);

                display1 = icon1.style.display;
                display2 = icon2.style.display;

                icon1.style.display = display2;
                icon2.style.display = display1;
            }
        //]]></script>

    </body>
</html>
EOF;
    }

    private function abbrClass($class)
    {
        $parts = explode('\\', $class);

        return sprintf("<abbr title=\"%s\">%s</abbr>", $class, array_pop($parts));
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    private function formatArgs(array $args)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf("<em>object</em>(%s)", $this->abbrClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf("<em>array</em>(%s)", is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('string'  === $item[0]) {
                $formattedValue = sprintf("'%s'", htmlspecialchars($item[1], ENT_QUOTES | ENT_SUBSTITUTE, $this->charset));
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(var_export($item[1], true)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', var_export(htmlspecialchars((string) $item[1], ENT_QUOTES | ENT_SUBSTITUTE, $this->charset), true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Back-ported from Symfony\Bridge\Twig\Extension\CodeExtension
     *
     * @param string  $file
     * @param int     $line
     *
     * @return string
     */
    private function fileExcerpt($file, $line)
    {
        if (is_readable($file)) {
            // highlight_file could throw warnings
            // see https://bugs.php.net/bug.php?id=25725
            $code = @highlight_file($file, true);
            // remove main code/span tags
            $code = preg_replace('#^<code.*?>\s*<span.*?>(.*)</span>\s*</code>#s', '\\1', $code);
            $content = preg_split('#<br />#', $code);

            $lines = array();
            for ($i = max($line - 3, 1), $max = min($line + 3, count($content)); $i <= $max; $i++) {
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'><code>'.$this->fixCodeMarkup($content[$i - 1]).'</code></li>';
            }

            return '<ol start="'.max($line - 3, 1).'">'.implode("\n", $lines).'</ol>';
        }
    }

    /**
     * Back-ported from Symfony\Bridge\Twig\Extension\CodeExtension
     *
     * @param string $line
     *
     * @return string
     */
    private function fixCodeMarkup($line)
    {
        // </span> ending tag from previous line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $closing && (false === $opening || $closing < $opening)) {
            $line = substr_replace($line, '', $closing, 7);
        }

        // missing </span> tag at the end of line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $opening && (false === $closing || $closing > $opening)) {
            $line .= '</span>';
        }

        return $line;
    }
}
