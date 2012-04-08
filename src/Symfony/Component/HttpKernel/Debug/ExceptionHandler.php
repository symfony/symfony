<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\FlattenException;

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
class ExceptionHandler
{
    private $debug;
    private $charset;

    public function __construct($debug = true, $charset = 'UTF-8')
    {
        $this->debug = $debug;
        $this->charset = $charset;
    }

    /**
     * Register the exception handler.
     *
     * @return The registered exception handler
     */
    static public function register($debug = true)
    {
        $handler = new static($debug);

        set_exception_handler(array($handler, 'handle'));

        return $handler;
    }

    /**
     * Sends a Response for the given Exception.
     *
     * @param \Exception $exception An \Exception instance
     */
    public function handle(\Exception $exception)
    {
        $this->createResponse($exception)->send();
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
        $content = '';
        $title = '';
        try {
            if (!$exception instanceof FlattenException) {
                $exception = FlattenException::create($exception);
            }

            switch ($exception->getStatusCode()) {
                case 404:
                    $title = 'Sorry, the page you are looking for could not be found.';
                    break;
                default:
                    $title = 'Whoops, looks like something went wrong.';
            }

            if ($this->debug) {
                $content = $this->getContent($exception);
            }
        } catch (\Exception $e) {
            // something nasty happened and we cannot throw an exception here anymore
            if ($this->debug) {
                $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($exception), $exception->getMessage());
            } else {
                $title = 'Whoops, looks like something went wrong.';
            }
        }

        return new Response($this->decorate($content, $title), $exception->getStatusCode());
    }

    private function getContent($exception)
    {
        $message = nl2br($exception->getMessage());
        $class = $this->abbrClass($exception->getClass());
        $count = count($exception->getAllPrevious());
        $content = '';
        foreach ($exception->toArray() as $position => $e) {
            $ind = $count - $position + 1;
            $total = $count + 1;
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
                }
                $content .= "</li>\n";
            }

            $content .= "    </ol>\n</div>\n";
        }

        return $content;
    }

    private function decorate($content, $title)
    {
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <meta name="robots" content="noindex,nofollow" />
        <title>{$title}</title>
        <style>
            /* Copyright (c) 2010, Yahoo! Inc. All rights reserved. Code licensed under the BSD License: http://developer.yahoo.com/yui/license.html */
            html{color:#000;background:#FFF;}body,div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,code,form,fieldset,legend,input,textarea,p,blockquote,th,td{margin:0;padding:0;}table{border-collapse:collapse;border-spacing:0;}fieldset,img{border:0;}address,caption,cite,code,dfn,em,strong,th,var{font-style:normal;font-weight:normal;}li{list-style:none;}caption,th{text-align:left;}h1,h2,h3,h4,h5,h6{font-size:100%;font-weight:normal;}q:before,q:after{content:'';}abbr,acronym{border:0;font-variant:normal;}sup{vertical-align:text-top;}sub{vertical-align:text-bottom;}input,textarea,select{font-family:inherit;font-size:inherit;font-weight:inherit;}input,textarea,select{*font-size:100%;}legend{color:#000;}

            html { background: #eee; padding: 10px }
            body { font: 11px Verdana, Arial, sans-serif; color: #333 }
            img { border: 0; }
            .clear { clear:both; height:0; font-size:0; line-height:0; }
            .clear_fix:after { display:block; height:0; clear:both; visibility:hidden; }
            .clear_fix { display:inline-block; }
            * html .clear_fix { height:1%; }
            .clear_fix { display:block; }
            #content { width:970px; margin:0 auto; }
            .sf-exceptionreset, .sf-exceptionreset .block { margin: auto }
            .sf-exceptionreset abbr { border-bottom: 1px dotted #000; cursor: help; }
            .sf-exceptionreset p { font-size:14px; line-height:20px; color:#868686; padding-bottom:20px }
            .sf-exceptionreset strong { font-weight:bold; }
            .sf-exceptionreset a { color:#6c6159; }
            .sf-exceptionreset a img { border:none; }
            .sf-exceptionreset a:hover { text-decoration:underline; }
            .sf-exceptionreset em { font-style:italic; }
            .sf-exceptionreset h1, .sf-exceptionreset h2 { font: 20px Georgia, "Times New Roman", Times, serif }
            .sf-exceptionreset h2 span { background-color: #fff; color: #333; padding: 6px; float: left; margin-right: 10px; }
            .sf-exceptionreset .traces li { font-size:12px; padding: 2px 4px; list-style-type:decimal; margin-left:20px; }
            .sf-exceptionreset .block { background-color:#FFFFFF; padding:10px 28px; margin-bottom:20px;
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
            .sf-exceptionreset .block_exception { background-color:#ddd; color: #333; padding:20px;
                -webkit-border-top-left-radius: 16px;
                -webkit-border-top-right-radius: 16px;
                -moz-border-radius-topleft: 16px;
                -moz-border-radius-topright: 16px;
                border-top-left-radius: 16px;
                border-top-right-radius: 16px;
                border-top:1px solid #ccc;
                border-right:1px solid #ccc;
                border-left:1px solid #ccc;
            }
            .sf-exceptionreset li a { background:none; color:#868686; text-decoration:none; }
            .sf-exceptionreset li a:hover { background:none; color:#313131; text-decoration:underline; }
            .sf-exceptionreset ol { padding: 10px 0; }
            .sf-exceptionreset h1 { background-color:#FFFFFF; padding: 15px 28px; margin-bottom: 20px;
                -webkit-border-radius: 10px;
                -moz-border-radius: 10px;
                border-radius: 10px;
                border: 1px solid #ccc;
            }
        </style>
    </head>
    <body>
        <div id="content" class="sf-exceptionreset">
            <h1>$title</h1>
$content
        </div>
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
    public function formatArgs(array $args)
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
}
