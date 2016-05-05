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

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Exception\OutOfMemoryException;

/**
 * ExceptionHandler converts an exception to a Response object.
 *
 * It is mostly useful in debug mode to replace the default PHP/XDebug
 * output with something prettier and more useful.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ExceptionHandler
{
    private $debug;
    private $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    private $fileLinkFormat;
    private $html;

    public function __construct($debug = true, $charset = null, $fileLinkFormat = null, $html = true)
    {
        $this->debug = $debug;
        $this->charset = $charset ?: ini_get('default_charset') ?: 'UTF-8';
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        $this->html = $html;
    }

    /**
     * Registers the exception handler.
     *
     * @param bool        $debug          Enable/disable debug mode, where the stack trace is displayed
     * @param string|null $charset        The charset used by exception messages
     * @param string|null $fileLinkFormat The IDE link template
     * @param bool        $html           Whether to display errors as HTML or plain text.
     *
     * @return ExceptionHandler The registered exception handler
     */
    public static function register($debug = true, $charset = null, $fileLinkFormat = null, $html = true)
    {
        $handler = new static($debug, $charset, $fileLinkFormat, $html);

        $prev = set_exception_handler(array($handler, 'handle'));
        if (is_array($prev) && $prev[0] instanceof ErrorHandler) {
            restore_exception_handler();
            $prev[0]->setExceptionHandler(array($handler, 'handle'));
        }

        return $handler;
    }

    /**
     * Sets a user exception handler.
     *
     * @param callable $handler An handler that will be called on Exception
     *
     * @return callable|null The previous exception handler if any
     */
    public function setHandler(callable $handler = null)
    {
        $old = $this->handler;
        $this->handler = $handler;

        return $old;
    }

    /**
     * Sets the format for links to source files.
     *
     * @param string $format The format for links to source files
     *
     * @return string The previous file link format.
     */
    public function setFileLinkFormat($format)
    {
        $old = $this->fileLinkFormat;
        $this->fileLinkFormat = $format;

        return $old;
    }

    /**
     * Sets the output format to HTML or plain text.
     *
     * @param bool $html TRUE if HTML should be returned.
     *
     * @return bool The previous output format.
     */
    public function setHtml($html)
    {
        $old = $this->html;
        $this->html = $html;

        return $old;
    }

    /**
     * Sends a response for the given Exception.
     *
     * To be as fail-safe as possible, the exception is first handled
     * by our simple exception handler, then by the user exception handler.
     * The latter takes precedence and any output from the former is cancelled,
     * if and only if nothing bad happens in this handling path.
     */
    public function handle(\Exception $exception)
    {
        if (null === $this->handler || $exception instanceof OutOfMemoryException) {
            $this->sendPhpResponse($exception);

            return;
        }

        $caughtLength = $this->caughtLength = 0;

        ob_start(function ($buffer) {
            $this->caughtBuffer = $buffer;

            return '';
        });

        $this->sendPhpResponse($exception);
        while (null === $this->caughtBuffer && ob_end_flush()) {
            // Empty loop, everything is in the condition
        }
        if (isset($this->caughtBuffer[0])) {
            ob_start(function ($buffer) {
                if ($this->caughtLength) {
                    // use substr_replace() instead of substr() for mbstring overloading resistance
                    $cleanBuffer = substr_replace($buffer, '', 0, $this->caughtLength);
                    if (isset($cleanBuffer[0])) {
                        $buffer = $cleanBuffer;
                    }
                }

                return $buffer;
            });

            echo $this->caughtBuffer;
            $caughtLength = ob_get_length();
        }
        $this->caughtBuffer = null;

        try {
            call_user_func($this->handler, $exception);
            $this->caughtLength = $caughtLength;
        } catch (\Exception $e) {
            if (!$caughtLength) {
                // All handlers failed. Let PHP handle that now.
                throw $exception;
            }
        }
    }

    /**
     * Sends the error associated with the given Exception as a plain PHP response.
     *
     * This method uses plain PHP functions like header() and echo to output
     * the response.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
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

            if ($this->html) {
                header('Content-Type: text/html; charset='.$this->charset);
            } else {
                header('Content-Type: text/plain; charset='.$this->charset);
            }
        }

        if ($this->html) {
            echo $this->getHtml($exception);
        } else {
            echo $this->getText($exception);
        }
    }

    /**
     * Gets the full HTML debug output for the given exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The HTML content
     */
    public function getHtml($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return $this->decorate($this->getContent($exception, true), $this->getStylesheet($exception));
    }

    /**
     * Gets the full plain-text debug output for the given exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The plain-text content
     */
    public function getText($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return $this->getContent($exception, false);
    }

    /**
     * Gets the full debug output for the given exception.
     *
     * @param FlattenException $exception A FlattenException instance
     * @param bool             $html      Whether to return HTML or plain text.
     *
     * @return string The content as an HTML or plain-text string
     */
    public function getContent(FlattenException $exception, $html = true)
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
                    $class = $this->formatClass($e['class'], $html);
                    $message = $html ? nl2br($this->escapeHtml($e['message'])) : $e['message'];
                    $trace = $this->formatTrace($e['trace'], $html);
                    if ($html) {
                        $formattedPath = $this->formatPath($e['trace'][0]['file'], $e['trace'][0]['line'], $html);
                        $content .= <<<EOF
                            <h2 class="block_exception clear_fix">
                                <span class="exception_counter">$ind/$total</span>
                                <span class="exception_title">$class $formattedPath:</span>
                                <span class="exception_message">$message</span>
                            </h2>
                            <div class="block">
                                $trace
                            </div>
EOF;
                    } else {
                        $content .= "$ind/$total $class\n    $message\n\n$trace";
                    }
                }
            } catch (\Exception $e) {
                // something nasty happened and we cannot throw an exception anymore
                if ($this->debug) {
                    $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $this->escapeHtml($e->getMessage()));
                } else {
                    $title = 'Whoops, looks like something went wrong.';
                }
            }
        }

        if ($html) {
            return <<<EOF
                <div id="sf-resetcontent" class="sf-reset">
                    <h1>$title</h1>
                    $content
                </div>
EOF;
        } else {
            return "$title\n\n$content\n";
        }
    }

    private function formatTrace(array $trace, $html)
    {
        $content = '';
        foreach ($trace as $trace) {
            $line = '';
            if ($trace['function']) {
                $line .= sprintf('at %s%s%s(%s)', $this->formatClass($trace['class'], $html), $trace['type'], $trace['function'], $this->formatArgs($trace['args'], $html));
            }
            if (isset($trace['file']) && isset($trace['line'])) {
                if ($line) {
                    $line .= ' ';
                }
                $line .= $this->formatPath($trace['file'], $trace['line'], $html);
            }
            $content .= $html ? "    <li>$line</li>\n" : "    $line\n";
        }

        if ($html) {
            return "<ol class=\"traces list_exception\">\n$content</ol>";
        } else {
            return $content;
        }
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
        return <<<'EOF'
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
            .sf-reset a { color:#6c6159; cursor: default; }
            .sf-reset a img { border:none; }
            .sf-reset a:hover { text-decoration:underline; }
            .sf-reset em { font-style:italic; }
            .sf-reset h1, .sf-reset h2 { font: 20px Georgia, "Times New Roman", Times, serif }
            .sf-reset .exception_counter { background-color: #fff; color: #333; padding: 6px; float: left; margin-right: 10px; float: left; display: block; }
            .sf-reset .exception_title { margin-left: 3em; margin-bottom: 0.7em; display: block; }
            .sf-reset .exception_message { margin-left: 3em; display: block; }
            .sf-reset .traces li { font-size:12px; padding: 2px 4px; list-style-type:decimal; margin-left:20px; }
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
            .sf-reset a { background:none; color:#868686; text-decoration:none; }
            .sf-reset a:hover { background:none; color:#313131; text-decoration:underline; }
            .sf-reset ol { padding: 10px 0; }
            .sf-reset h1 { background-color:#FFFFFF; padding: 15px 28px; margin-bottom: 20px;
                -webkit-border-radius: 10px;
                -moz-border-radius: 10px;
                border-radius: 10px;
                border: 1px solid #ccc;
            }
EOF;
    }

    private function decorate($content, $css)
    {
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="{$this->charset}" />
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
    </body>
</html>
EOF;
    }

    private function formatClass($class, $html)
    {
        $parts = explode('\\', $class);

        if ($html) {
            return sprintf('<abbr title="%s">%s</abbr>', $class, array_pop($parts));
        } else {
            return array_pop($parts);
        }
    }

    private function formatPath($path, $line, $html)
    {
        if ($html) {
            $path = $this->escapeHtml($path);
            $file = preg_match('#[^/\\\\]*$#', $path, $file) ? $file[0] : $path;

            if ($linkFormat = $this->fileLinkFormat) {
                $link = strtr($this->escapeHtml($linkFormat), array('%f' => $path, '%l' => (int) $line));

                return sprintf('in <a href="%s" title="Go to source">%s line %d</a>', $link, $file, $line);
            }

            return sprintf('in <a title="%s line %3$d" ondblclick="var f=this.innerHTML;this.innerHTML=this.title;this.title=f;">%s line %d</a>', $path, $file, $line);
        } else {
            return sprintf('in %s:%s', $path, $line);
        }
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    private function formatArgs(array $args, $html)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $pattern = $html ? '<em>object</em>(%s)' : 'object(%s)';
                $formattedValue = sprintf($pattern, $this->formatClass($item[1], $html));
            } elseif ('array' === $item[0]) {
                $pattern = $html ? '<em>array</em>(%s)' : 'array(%s)';
                $formattedValue = sprintf($pattern, is_array($item[1]) ? $this->formatArgs($item[1], $html) : $item[1]);
            } elseif ('string' === $item[0]) {
                $formattedValue = sprintf("'%s'", $html ? $this->escapeHtml($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = $html ? '<em>null</em>' : 'null';
            } elseif ('boolean' === $item[0]) {
                $pattern = $html ? '<em>%s</em>' : '%s';
                $formattedValue = sprintf($pattern, strtolower(var_export($item[1], true)));
            } elseif ('resource' === $item[0]) {
                $formattedValue = $html ? '<em>resource</em>' : 'resource';
            } else {
                $s = $html ? $this->escapeHtml((string) $item[1]) : $item[1];
                $formattedValue = str_replace("\n", '', var_export($s, true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * HTML-encodes a string.
     */
    private function escapeHtml($str)
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, $this->charset);
    }
}
