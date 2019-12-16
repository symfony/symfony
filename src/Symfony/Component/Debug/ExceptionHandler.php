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
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', ExceptionHandler::class, \Symfony\Component\ErrorHandler\ErrorHandler::class), E_USER_DEPRECATED);

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
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final since Symfony 4.3
 *
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\ErrorHandler instead.
 */
class ExceptionHandler
{
    private const GHOST_ADDONS = [
        '02-14' => self::GHOST_HEART,
        '02-29' => self::GHOST_PLUS,
        '10-18' => self::GHOST_GIFT,
    ];

    private const GHOST_GIFT = 'M124.005 5.36c.396-.715 1.119-1.648-.124-1.873-.346-.177-.692-.492-1.038-.141-.769.303-1.435.728-.627 1.523.36.514.685 1.634 1.092 1.758.242-.417.47-.842.697-1.266zm-1.699 1.977c-.706-1.26-1.274-2.612-2.138-3.774-1.051-1.123-3.122-.622-3.593.825-.625 1.431.724 3.14 2.251 2.96 1.159.02 2.324.072 3.48-.011zm5.867.043c1.502-.202 2.365-2.092 1.51-3.347-.757-1.34-2.937-1.387-3.698-.025-.659 1.1-1.23 2.25-1.835 3.38 1.336.077 2.686.06 4.023-.008zm2.487 1.611c.512-.45 2.494-.981.993-1.409-.372-.105-.805-.59-1.14-.457-.726.902-1.842 1.432-3.007 1.376-.228.075-1.391-.114-1.077.1.822.47 1.623.979 2.474 1.395.595-.317 1.173-.667 1.757-1.005zm-11.696.255l1.314-.765c-1.338-.066-2.87.127-3.881-.95-.285-.319-.559-.684-.954-.282-.473.326-1.929.66-.808 1.058.976.576 1.945 1.167 2.946 1.701.476-.223.926-.503 1.383-.762zm6.416 2.846c.567-.456 1.942-.89 1.987-1.38-1.282-.737-2.527-1.56-3.87-2.183-.461-.175-.835.094-1.207.328-1.1.654-2.225 1.267-3.288 1.978 1.39.86 2.798 1.695 4.219 2.504.725-.407 1.44-.83 2.16-1.247zm5.692 1.423l1.765-1.114c-.005-1.244.015-2.488-.019-3.732a77.306 77.306 0 0 0-3.51 2.084c-.126 1.282-.062 2.586-.034 3.876.607-.358 1.2-.741 1.798-1.114zm-13.804-.784c.06-1.06.19-2.269-1.09-2.583-.807-.376-1.926-1.341-2.548-1.332-.02 1.195-.01 2.39-.011 3.585 1.192.744 2.364 1.524 3.582 2.226.119-.616.041-1.269.067-1.896zm8.541 4.105l2.117-1.336c-.003-1.284.05-2.57-.008-3.853-.776.223-1.662.91-2.48 1.337l-1.834 1.075c.012 1.37-.033 2.744.044 4.113.732-.427 1.443-.887 2.161-1.336zm-2.957-.72v-2.057c-1.416-.828-2.828-1.664-4.25-2.482-.078 1.311-.033 2.627-.045 3.94 1.416.887 2.817 1.798 4.25 2.655.057-.683.036-1.372.045-2.057zm8.255 2.755l1.731-1.153c-.024-1.218.06-2.453-.062-3.658-1.2.685-2.358 1.464-3.537 2.195.028 1.261-.058 2.536.072 3.786.609-.373 1.2-.777 1.796-1.17zm-13.851-.683l-.014-1.916c-1.193-.746-2.37-1.517-3.58-2.234-.076 1.224-.033 2.453-.044 3.679 1.203.796 2.392 1.614 3.61 2.385.048-.636.024-1.276.028-1.914zm8.584 4.199l2.102-1.396c-.002-1.298.024-2.596-.01-3.893-1.427.88-2.843 1.775-4.25 2.686-.158 1.253-.055 2.545-.056 3.811.437.266 1.553-.912 2.214-1.208zm-2.988-.556c-.085-.894.365-2.154-.773-2.5-1.146-.727-2.288-1.46-3.45-2.163-.17 1.228.008 2.508-.122 3.751a79.399 79.399 0 0 0 4.278 2.885c.117-.641.044-1.32.067-1.973zm-4.872-.236l-5.087-3.396c.002-3.493-.047-6.988.015-10.48.85-.524 1.753-.954 2.627-1.434-.564-1.616.25-3.58 1.887-4.184 1.372-.563 3.025-.055 3.9 1.13l1.906-.978 1.916.987c.915-1.086 2.483-1.706 3.842-1.097 1.631.573 2.52 2.532 1.936 4.145.88.497 1.837.886 2.644 1.492.036 3.473 0 6.946-.003 10.419-3.374 2.233-6.693 4.55-10.122 6.699-.997 0-1.858-1.083-2.783-1.522a735.316 735.316 0 0 1-2.678-1.781z';
    private const GHOST_HEART = 'M125.914 8.305c3.036-8.71 14.933 0 0 11.2-14.932-11.2-3.036-19.91 0-11.2z';
    private const GHOST_PLUS = 'M111.368 8.97h7.324V1.645h7.512v7.323h7.324v7.513h-7.324v7.323h-7.512v-7.323h-7.324z';

    private $debug;
    private $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    private $fileLinkFormat;

    public function __construct(bool $debug = true, string $charset = null, $fileLinkFormat = null)
    {
        $this->debug = $debug;
        $this->charset = $charset ?: ini_get('default_charset') ?: 'UTF-8';
        $this->fileLinkFormat = $fileLinkFormat;
    }

    /**
     * Registers the exception handler.
     *
     * @param bool        $debug          Enable/disable debug mode, where the stack trace is displayed
     * @param string|null $charset        The charset used by exception messages
     * @param string|null $fileLinkFormat The IDE link template
     *
     * @return static
     */
    public static function register($debug = true, $charset = null, $fileLinkFormat = null)
    {
        $handler = new static($debug, $charset, $fileLinkFormat);

        $prev = set_exception_handler([$handler, 'handle']);
        if (\is_array($prev) && $prev[0] instanceof ErrorHandler) {
            restore_exception_handler();
            $prev[0]->setExceptionHandler([$handler, 'handle']);
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
     * @param string|FileLinkFormatter $fileLinkFormat The format for links to source files
     *
     * @return string The previous file link format
     */
    public function setFileLinkFormat($fileLinkFormat)
    {
        $old = $this->fileLinkFormat;
        $this->fileLinkFormat = $fileLinkFormat;

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
            ($this->handler)($exception);
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
     * @param \Throwable|FlattenException $exception A \Throwable or FlattenException instance
     */
    public function sendPhpResponse($exception)
    {
        if ($exception instanceof \Throwable) {
            $exception = FlattenException::createFromThrowable($exception);
        }

        if (!headers_sent()) {
            header(sprintf('HTTP/1.0 %s', $exception->getStatusCode()));
            foreach ($exception->getHeaders() as $name => $value) {
                header($name.': '.$value, false);
            }
            header('Content-Type: text/html; charset='.$this->charset);
        }

        echo $this->decorate($this->getContent($exception), $this->getStylesheet($exception));
    }

    /**
     * Gets the full HTML content associated with the given exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The HTML content as a string
     */
    public function getHtml($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return $this->decorate($this->getContent($exception), $this->getStylesheet($exception));
    }

    /**
     * Gets the HTML content associated with the given exception.
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
                $title = $this->debug ? $this->escapeHtml($exception->getMessage()) : 'Whoops, looks like something went wrong.';
        }

        if (!$this->debug) {
            return <<<EOF
                <div class="container">
                    <h1>$title</h1>
                </div>
EOF;
        }

        $content = '';
        try {
            $count = \count($exception->getAllPrevious());
            $total = $count + 1;
            foreach ($exception->toArray() as $position => $e) {
                $ind = $count - $position + 1;
                $class = $this->formatClass($e['class']);
                $message = nl2br($this->escapeHtml($e['message']));
                $content .= sprintf(<<<'EOF'
                    <div class="trace trace-as-html">
                        <table class="trace-details">
                            <thead class="trace-head"><tr><th>
                                <h3 class="trace-class">
                                    <span class="text-muted">(%d/%d)</span>
                                    <span class="exception_title">%s</span>
                                </h3>
                                <p class="break-long-words trace-message">%s</p>
                            </th></tr></thead>
                            <tbody>
EOF
                    , $ind, $total, $class, $message);
                foreach ($e['trace'] as $trace) {
                    $content .= '<tr><td>';
                    if ($trace['function']) {
                        $content .= sprintf('at <span class="trace-class">%s</span><span class="trace-type">%s</span><span class="trace-method">%s</span>', $this->formatClass($trace['class']), $trace['type'], $trace['function']);

                        if (isset($trace['args'])) {
                            $content .= sprintf('(<span class="trace-arguments">%s</span>)', $this->formatArgs($trace['args']));
                        }
                    }
                    if (isset($trace['file']) && isset($trace['line'])) {
                        $content .= $this->formatPath($trace['file'], $trace['line']);
                    }
                    $content .= "</td></tr>\n";
                }

                $content .= "</tbody>\n</table>\n</div>\n";
            }
        } catch (\Exception $e) {
            // something nasty happened and we cannot throw an exception anymore
            if ($this->debug) {
                $e = FlattenException::create($e);
                $title = sprintf('Exception thrown when handling an exception (%s: %s)', $e->getClass(), $this->escapeHtml($e->getMessage()));
            } else {
                $title = 'Whoops, looks like something went wrong.';
            }
        }

        $symfonyGhostImageContents = $this->getSymfonyGhostAsSvg();

        return <<<EOF
            <div class="exception-summary">
                <div class="container">
                    <div class="exception-message-wrapper">
                        <h1 class="break-long-words exception-message">$title</h1>
                        <div class="exception-illustration hidden-xs-down">$symfonyGhostImageContents</div>
                    </div>
                </div>
            </div>

            <div class="container">
                $content
            </div>
EOF;
    }

    /**
     * Gets the stylesheet associated with the given exception.
     *
     * @return string The stylesheet as a string
     */
    public function getStylesheet(FlattenException $exception)
    {
        if (!$this->debug) {
            return <<<'EOF'
                body { background-color: #fff; color: #222; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; }
                .container { margin: 30px; max-width: 600px; }
                h1 { color: #dc3545; font-size: 24px; }
EOF;
        }

        return <<<'EOF'
            body { background-color: #F9F9F9; color: #222; font: 14px/1.4 Helvetica, Arial, sans-serif; margin: 0; padding-bottom: 45px; }

            a { cursor: pointer; text-decoration: none; }
            a:hover { text-decoration: underline; }
            abbr[title] { border-bottom: none; cursor: help; text-decoration: none; }

            code, pre { font: 13px/1.5 Consolas, Monaco, Menlo, "Ubuntu Mono", "Liberation Mono", monospace; }

            table, tr, th, td { background: #FFF; border-collapse: collapse; vertical-align: top; }
            table { background: #FFF; border: 1px solid #E0E0E0; box-shadow: 0px 0px 1px rgba(128, 128, 128, .2); margin: 1em 0; width: 100%; }
            table th, table td { border: solid #E0E0E0; border-width: 1px 0; padding: 8px 10px; }
            table th { background-color: #E0E0E0; font-weight: bold; text-align: left; }

            .hidden-xs-down { display: none; }
            .block { display: block; }
            .break-long-words { -ms-word-break: break-all; word-break: break-all; word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; }
            .text-muted { color: #999; }

            .container { max-width: 1024px; margin: 0 auto; padding: 0 15px; }
            .container::after { content: ""; display: table; clear: both; }

            .exception-summary { background: #B0413E; border-bottom: 2px solid rgba(0, 0, 0, 0.1); border-top: 1px solid rgba(0, 0, 0, .3); flex: 0 0 auto; margin-bottom: 30px; }

            .exception-message-wrapper { display: flex; align-items: center; min-height: 70px; }
            .exception-message { flex-grow: 1; padding: 30px 0; }
            .exception-message, .exception-message a { color: #FFF; font-size: 21px; font-weight: 400; margin: 0; }
            .exception-message.long { font-size: 18px; }
            .exception-message a { border-bottom: 1px solid rgba(255, 255, 255, 0.5); font-size: inherit; text-decoration: none; }
            .exception-message a:hover { border-bottom-color: #ffffff; }

            .exception-illustration { flex-basis: 111px; flex-shrink: 0; height: 66px; margin-left: 15px; opacity: .7; }

            .trace + .trace { margin-top: 30px; }
            .trace-head .trace-class { color: #222; font-size: 18px; font-weight: bold; line-height: 1.3; margin: 0; position: relative; }

            .trace-message { font-size: 14px; font-weight: normal; margin: .5em 0 0; }

            .trace-file-path, .trace-file-path a { color: #222; margin-top: 3px; font-size: 13px; }
            .trace-class { color: #B0413E; }
            .trace-type { padding: 0 2px; }
            .trace-method { color: #B0413E; font-weight: bold; }
            .trace-arguments { color: #777; font-weight: normal; padding-left: 2px; }

            @media (min-width: 575px) {
                .hidden-xs-down { display: initial; }
            }
EOF;
    }

    private function decorate(string $content, string $css): string
    {
        return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="{$this->charset}" />
        <meta name="robots" content="noindex,nofollow" />
        <style>$css</style>
    </head>
    <body>
        $content
    </body>
</html>
EOF;
    }

    private function formatClass(string $class): string
    {
        $parts = explode('\\', $class);

        return sprintf('<abbr title="%s">%s</abbr>', $class, array_pop($parts));
    }

    private function formatPath(string $path, int $line): string
    {
        $file = $this->escapeHtml(preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path);
        $fmt = $this->fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');

        if (!$fmt) {
            return sprintf('<span class="block trace-file-path">in <span title="%s%3$s"><strong>%s</strong>%s</span></span>', $this->escapeHtml($path), $file, 0 < $line ? ' line '.$line : '');
        }

        if (\is_string($fmt)) {
            $i = strpos($f = $fmt, '&', max(strrpos($f, '%f'), strrpos($f, '%l'))) ?: \strlen($f);
            $fmt = [substr($f, 0, $i)] + preg_split('/&([^>]++)>/', substr($f, $i), -1, PREG_SPLIT_DELIM_CAPTURE);

            for ($i = 1; isset($fmt[$i]); ++$i) {
                if (0 === strpos($path, $k = $fmt[$i++])) {
                    $path = substr_replace($path, $fmt[$i], 0, \strlen($k));
                    break;
                }
            }

            $link = strtr($fmt[0], ['%f' => $path, '%l' => $line]);
        } else {
            try {
                $link = $fmt->format($path, $line);
            } catch (\Exception $e) {
                return sprintf('<span class="block trace-file-path">in <span title="%s%3$s"><strong>%s</strong>%s</span></span>', $this->escapeHtml($path), $file, 0 < $line ? ' line '.$line : '');
            }
        }

        return sprintf('<span class="block trace-file-path">in <a href="%s" title="Go to source"><strong>%s</string>%s</a></span>', $this->escapeHtml($link), $file, 0 < $line ? ' line '.$line : '');
    }

    /**
     * Formats an array as a string.
     */
    private function formatArgs(array $args): string
    {
        $result = [];
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf('<em>object</em>(%s)', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('<em>array</em>(%s)', \is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(var_export($item[1], true)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', $this->escapeHtml(var_export($item[1], true)));
            }

            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", $this->escapeHtml($key), $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * HTML-encodes a string.
     */
    private function escapeHtml(string $str): string
    {
        return htmlspecialchars($str, ENT_COMPAT | ENT_SUBSTITUTE, $this->charset);
    }

    private function getSymfonyGhostAsSvg(): string
    {
        return '<svg viewBox="0 0 136 81" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.4"><path d="M92.4 20.4a23.2 23.2 0 0 1 9 1.9 23.7 23.7 0 0 1 5.2 3 24.3 24.3 0 0 1 3.4 3.4 24.8 24.8 0 0 1 5 9.4c.5 1.7.8 3.4 1 5.2v14.5h.4l.5.2a7.4 7.4 0 0 0 2.5.2l.2-.2.6-.8.8-1.3-.2-.1a5.5 5.5 0 0 1-.8-.3 5.6 5.6 0 0 1-2.3-1.8 5.7 5.7 0 0 1-.9-1.6 6.5 6.5 0 0 1-.2-2.8 7.3 7.3 0 0 1 .5-2l.3-.3.8-.9.3-.3c.2-.2.5-.3.8-.3H120.7c.2 0 .3-.1.4 0h.4l.2.1.3.2.2-.4.3-.4.1-.1 1.2-1 .3-.2.4-.1.4-.1h.3l1.5.1.4.1.8.5.1.2 1 1.1v.2H129.4l.4-.2 1.4-.5h1.1c.3 0 .7.2 1 .4.2 0 .3.2.5.3l.2.2.5.3.4.6.1.3.4 1.4.1.4v.6a7.8 7.8 0 0 1-.1.6 9.9 9.9 0 0 1-.8 2.4 7.8 7.8 0 0 1-3 3.3 6.4 6.4 0 0 1-1 .5 6.1 6.1 0 0 1-.6.2l-.7.1h-.1a23.4 23.4 0 0 1-.2 1.7 14.3 14.3 0 0 1-.6 2.1l-.8 2a9.2 9.2 0 0 1-.4.6l-.7 1a9.1 9.1 0 0 1-2.3 2.2c-.9.5-2 .6-3 .7l-1.4.1h-.5l-.4.1a15.8 15.8 0 0 1-2.8-.1v4.2a9.7 9.7 0 0 1-.7 3.5 9.6 9.6 0 0 1-1.7 2.8 9.3 9.3 0 0 1-3 2.3 9 9 0 0 1-5.4.7 9 9 0 0 1-3-1 9.4 9.4 0 0 1-2.7-2.5 10 10 0 0 1-1 1.2 9.3 9.3 0 0 1-2 1.3 9 9 0 0 1-2.4 1 9 9 0 0 1-6.5-1.1A9.4 9.4 0 0 1 85 77V77a10.9 10.9 0 0 1-.6.6 9.3 9.3 0 0 1-2.7 2 9 9 0 0 1-6 .8 9 9 0 0 1-2.4-1 9.3 9.3 0 0 1-2.3-1.7 9.6 9.6 0 0 1-1.8-2.8 9.7 9.7 0 0 1-.8-3.7v-4a18.5 18.5 0 0 1-2.9.2l-1.2-.1c-1.9-.3-3.7-1-5.1-2.1A8.2 8.2 0 0 1 58 64a10.2 10.2 0 0 1-.9-1.2 15.3 15.3 0 0 1-.7-1.3 20.8 20.8 0 0 1-1.9-6.2v-.2a6.5 6.5 0 0 1-1-.3 6.1 6.1 0 0 1-.6-.3 6.6 6.6 0 0 1-.9-.5 8.2 8.2 0 0 1-2.7-3.8 10 10 0 0 1-.3-1 10.3 10.3 0 0 1-.3-1.9V47v-.4l.1-.4.6-1.4.1-.2a2 2 0 0 1 .8-.8l.3-.2.3-.2a3.2 3.2 0 0 1 1.8-.5h.4l.3.2 1.4.6.2.2.4.3.3.4.7-.7.2-.2.4-.2.6-.2h2.1l.4.2.4.2.3.2.8 1 .2-.1h.1v-.1H63l1.1.1h.3l.8.5.3.4.7 1 .2.3.1.5a11 11 0 0 1 .2 1.5c0 .8 0 1.6-.3 2.3a6 6 0 0 1-.5 1.2 5.5 5.5 0 0 1-3.3 2.5 12.3 12.3 0 0 0 1.4 3h.1l.2.1 1 .2h1.5l.5-.2H67.8l.5-.2h.1V44v-.4a26.7 26.7 0 0 1 .3-2.3 24.7 24.7 0 0 1 5.7-12.5 24.2 24.2 0 0 1 3.5-3.3 23.7 23.7 0 0 1 4.9-3 23.2 23.2 0 0 1 5.6-1.7 23.7 23.7 0 0 1 4-.3zm-.3 2a21.2 21.2 0 0 0-8 1.7 21.6 21.6 0 0 0-4.8 2.7 22.2 22.2 0 0 0-3.2 3 22.7 22.7 0 0 0-5 9.2 23.4 23.4 0 0 0-.7 4.9v15.7l-.5.1a34.3 34.3 0 0 1-1.5.3h-.2l-.4.1h-.4l-.9.2a10 10 0 0 1-1.9 0c-.5 0-1-.2-1.5-.4a1.8 1.8 0 0 1-.3-.2 2 2 0 0 1-.3-.3 5.2 5.2 0 0 1-.1-.2 9 9 0 0 1-.6-.9 13.8 13.8 0 0 1-1-2 14.3 14.3 0 0 1-.6-2 14 14 0 0 1-.1-.8v-.2h.3a12.8 12.8 0 0 0 1.4-.2 4.4 4.4 0 0 0 .3 0 3.6 3.6 0 0 0 1.1-.7 3.4 3.4 0 0 0 1.2-1.7l.2-1.2a5.1 5.1 0 0 0 0-.8 7.2 7.2 0 0 0-.1-.8l-.7-1-1.2-.2-1 .7-.1 1.3a5 5 0 0 1 .1.4v.6a1 1 0 0 1 0 .3c-.1.3-.4.4-.7.5l-1.2.4v-.7A9.9 9.9 0 0 1 60 49l.3-.6v-.2l.1-.1v-1.6l-1-1.2h-1.5l-1 1.1v.4a5.3 5.3 0 0 0-.2.6 5.5 5.5 0 0 0 0 .5c0 .7 0 1.4.3 2 0 .4.2.8.4 1.2L57 51a9.5 9.5 0 0 1-1.1-.5h-.2a2 2 0 0 1-.4-.3c-.4-.4-.5-1-.6-1.6a5.6 5.6 0 0 1 0-.5v-.5-.5l-.6-1.5-1.4-.6-.9.3s-.2 0-.3.2a2 2 0 0 1-.1 0l-.6 1.4v.7a8.5 8.5 0 0 0 .5 2c.4 1.1 1 2.1 2 2.8a4.7 4.7 0 0 0 2.1.9h1a22.8 22.8 0 0 0 .1 1 18.1 18.1 0 0 0 .8 3.8 18.2 18.2 0 0 0 1.6 3.7l1 1.3c1 1 2.3 1.6 3.7 2a11.7 11.7 0 0 0 4.8 0h.4l.5-.2.5-.1.6-.2v6.6a8 8 0 0 0 .1 1.3 7.5 7.5 0 0 0 2.4 4.3 7.2 7.2 0 0 0 2.3 1.3 7 7 0 0 0 7-1.1 7.5 7.5 0 0 0 2-2.6A7.7 7.7 0 0 0 85 72V71a8.2 8.2 0 0 0 .2 1.3c0 .7.3 1.4.6 2a7.5 7.5 0 0 0 1.7 2.3 7.3 7.3 0 0 0 2.2 1.4 7.1 7.1 0 0 0 4.6.2 7.2 7.2 0 0 0 2.4-1.2 7.5 7.5 0 0 0 2.1-2.7 7.8 7.8 0 0 0 .7-2.4V71a9.3 9.3 0 0 0 .1.6 7.6 7.6 0 0 0 .6 2.5 7.5 7.5 0 0 0 2.4 3 7.1 7.1 0 0 0 7 .8 7.3 7.3 0 0 0 2.3-1.5 7.5 7.5 0 0 0 1.6-2.3 7.6 7.6 0 0 0 .5-2l.1-1.1v-6.7l.4.1a12.2 12.2 0 0 0 2 .5 11.1 11.1 0 0 0 2.5 0h.8l1.2-.1a9.5 9.5 0 0 0 1.4-.2l.9-.3a3.5 3.5 0 0 0 .6-.4l1.2-1.4a12.2 12.2 0 0 0 .8-1.2c0-.3.2-.5.3-.7a15.9 15.9 0 0 0 .7-2l.3-1.6v-1.3l.2-.9V54.6a15.5 15.5 0 0 0 1.8 0 4.5 4.5 0 0 0 1.4-.5 5.7 5.7 0 0 0 2.5-3.2 7.6 7.6 0 0 0 .4-1.5v-.3l-.4-1.4a5.2 5.2 0 0 1-.2-.1l-.4-.4a3.8 3.8 0 0 0-.2 0 1.4 1.4 0 0 0-.5-.2l-1.4.4-.7 1.3v.7a5.7 5.7 0 0 1-.1.8l-.7 1.4a1.9 1.9 0 0 1-.5.3h-.3a9.6 9.6 0 0 1-.8.3 8.8 8.8 0 0 1-.6 0l.2-.4.2-.5.2-.3v-.4l.1-.2V50l.1-1 .1-.6v-.6a4.8 4.8 0 0 0 0-.8v-.2l-1-1.1-1.5-.2-1.1 1-.2 1.4v.1l.2.4.2.3v.4l.1 1.1v.3l.1.5v.8a9.6 9.6 0 0 1-.8-.3l-.2-.1h-.3l-.8-.1h-.2a1.6 1.6 0 0 1-.2-.2.9.9 0 0 1-.2-.2 1 1 0 0 1-.1-.5l.2-.9v-1.2l-.9-.8h-1.2l-.8.9v.3a4.8 4.8 0 0 0-.3 2l.3.9a3.5 3.5 0 0 0 1.2 1.6l1 .5.8.2 1.4.1h.4l.2.1a12.1 12.1 0 0 1-1 2.6 13.2 13.2 0 0 1-.8 1.5 9.5 9.5 0 0 1-1 1.2l-.2.3a1.7 1.7 0 0 1-.4.3 2.4 2.4 0 0 1-.7.2h-2.5a7.8 7.8 0 0 1-.6-.2l-.7-.2h-.2a14.8 14.8 0 0 1-.6-.2 23.4 23.4 0 0 1-.4-.1l-.4-.1-.3-.1V43.9a34.6 34.6 0 0 0 0-.6 23.6 23.6 0 0 0-.4-3 22.7 22.7 0 0 0-1.5-4.7 22.6 22.6 0 0 0-4.6-6.7 21.9 21.9 0 0 0-6.9-4.7 21.2 21.2 0 0 0-8.1-1.8H92zm9.1 33.7l.3.1a1 1 0 0 1 .6.8v.4a8.4 8.4 0 0 1 0 .5 8.8 8.8 0 0 1-1.6 4.2l-1 1.3A10 10 0 0 1 95 66c-1.3.3-2.7.4-4 .3a10.4 10.4 0 0 1-2.7-.8 10 10 0 0 1-3.6-2.5 9.3 9.3 0 0 1-.8-1 9 9 0 0 1-.7-1.2 8.6 8.6 0 0 1-.8-3.4V57a1 1 0 0 1 .3-.6 1 1 0 0 1 1.3-.2 1 1 0 0 1 .4.8v.4a6.5 6.5 0 0 0 .5 2.2 7 7 0 0 0 2.1 2.8l1 .6c2.6 1.6 6 1.6 8.5 0a8 8 0 0 0 1.1-.6 7.6 7.6 0 0 0 1.2-1.2 7 7 0 0 0 1-1.7 6.5 6.5 0 0 0 .4-2.5 1 1 0 0 1 .7-1h.4zM30.7 43.7c-15.5 1-28.5-6-30.1-16.4C-1.2 15.7 11.6 4 29 1.3 46.6-1.7 62.3 5.5 64 17.1c1.6 10.4-8.7 21-23.7 25a31.2 31.2 0 0 0 0 .9v.3a19 19 0 0 0 .1 1l.1.4.1.9a4.7 4.7 0 0 0 .5 1l.7 1a9.2 9.2 0 0 0 1.2 1l1.5.8.6.8-.7.6-1.1.3a11.2 11.2 0 0 1-2.6.4 8.6 8.6 0 0 1-3-.5 8.5 8.5 0 0 1-1-.4 11.2 11.2 0 0 1-1.8-1.2 13.3 13.3 0 0 1-1-1 18 18 0 0 1-.7-.6l-.4-.4a23.4 23.4 0 0 1-1.3-1.8l-.1-.1-.3-.5V45l-.3-.6v-.7zM83.1 36c3.6 0 6.5 3.2 6.5 7.1 0 4-3 7.2-6.5 7.2S76.7 47 76.7 43 79.6 36 83 36zm18 0c3.6 0 6.5 3.2 6.5 7.1 0 4-2.9 7.2-6.4 7.2S94.7 47 94.7 43s3-7.1 6.5-7.1zm-18 6.1c2 0 3.5 1.6 3.5 3.6S85 49.2 83 49.2s-3.4-1.6-3.4-3.6S81.2 42 83 42zm17.9 0c1.9 0 3.4 1.6 3.4 3.6s-1.5 3.6-3.4 3.6c-2 0-3.5-1.6-3.5-3.6S99.1 42 101 42zM17 28c-.3 1.6-1.8 5-5.2 5.8-2.5.6-4.1-.8-4.5-2.6-.4-1.9.7-3.5 2.1-4.5A3.5 3.5 0 0 1 8 24.6c-.4-2 .8-3.7 3.2-4.2 1.9-.5 3.1.2 3.4 1.5.3 1.1-.5 2.2-1.8 2.5-.9.3-1.6 0-1.7-.6a1.4 1.4 0 0 1 0-.7s.3.2 1 0c.7-.1 1-.7.9-1.2-.2-.6-1-.8-1.8-.6-1 .2-2 1-1.7 2.6.3 1 .9 1.6 1.5 1.8l.7-.2c1-.2 1.5 0 1.6.5 0 .4-.2 1-1.2 1.2a3.3 3.3 0 0 1-1.5 0c-.9.7-1.6 1.9-1.3 3.2.3 1.3 1.3 2.2 3 1.8 2.5-.7 3.8-3.7 4.2-5-.3-.5-.6-1-.7-1.6-.1-.5.1-1 .9-1.2.4 0 .7.2.8.8a2.8 2.8 0 0 1 0 1l.7 1c.6-2 1.4-4 1.7-4 .6-.2 1.5.6 1.5.6-.8.7-1.7 2.4-2.3 4.2.8.6 1.6 1 2.1 1 .5-.1.8-.6 1-1.2-.3-2.2 1-4.3 2.3-4.6.7-.2 1.3.2 1.4.8.1.5 0 1.3-.9 1.7-.2-1-.6-1.3-1-1.3-.4.1-.7 1.4-.4 2.8.2 1 .7 1.5 1.3 1.4.8-.2 1.3-1.2 1.7-2.1-.3-2.1.9-4.2 2.2-4.5.7-.2 1.2.1 1.4 1 .4 1.4-1 2.8-2.2 3.4.3.7.7 1 1.3.9 1-.3 1.6-1.5 2-2.5l-.5-3v-.3s1.6-.3 1.8.6v.1c.2-.6.7-1.2 1.3-1.4.8-.1 1.5.6 1.7 1.6.5 2.2-.5 4.4-1.8 4.7H33a31.9 31.9 0 0 0 1 5.2c-.4.1-1.8.4-2-.4l-.5-5.6c-.5 1-1.3 2.2-2.5 2.4-1 .3-1.6-.3-2-1.1-.5 1-1.3 2.1-2.4 2.4-.8.2-1.5-.1-2-1-.3.8-.9 1.5-1.5 1.7-.7.1-1.5-.3-2.4-1-.3.8-.4 1.6-.4 2.2 0 0-.7 0-.8-.4-.1-.5 0-1.5.3-2.7a10.3 10.3 0 0 1-.7-.8zm38.2-17.8l.2.9c.5 1.9.4 4.4.8 6.4 0 .6-.4 3-1.4 3.3-.2 0-.3 0-.4-.4-.1-.7 0-1.6-.3-2.6-.2-1.1-.8-1.6-1.5-1.5-.8.2-1.3 1-1.6 2l-.1-.5c-.2-1-1.8-.6-1.8-.6a6.2 6.2 0 0 1 .4 1.3l.2 1c-.2.5-.6 1-1.2 1l-.2.1a7 7 0 0 0-.1-.8c-.3-1.1-1-2-1.6-1.8a.7.7 0 0 0-.4.3c-1.3.3-2.4 2-2.1 3.9-.2.9-.6 1.7-1 1.9-.5 0-.8-.5-1.1-1.8l-.1-1.2a4 4 0 0 0 0-1.7c0-.4-.4-.7-.8-.6-.7.2-.9 1.7-.5 3.8-.2 1-.6 2-1.3 2-.4.2-.8-.2-1-1l-.2-3c1.2-.5 2-1 1.8-1.7-.1-.5-.8-.7-.8-.7s0 .7-1 1.2l-.2-1.4c-.1-.6-.4-1-1.7-.6l.4 1 .2 1.5h-1v.8c0 .3.4.3 1 .2 0 1.3 0 2.7.2 3.6.3 1.4 1.2 2 2 1.7 1-.2 1.6-1.3 2-2.3.3 1.2 1 2 1.9 1.7.7-.2 1.2-1.1 1.6-2.2.4.8 1.1 1.1 2 1 1.2-.4 1.7-1.6 1.8-2.8h.2c.6-.2 1-.6 1.3-1 0 .8 0 1.5.2 2.1.1.5.3.7.6.6.5-.1 1-.9 1-.9a4 4 0 0 1-.3-1c-.3-1.3.3-3.6 1-3.7.2 0 .3.2.5.7v.8l.2 1.5v.7c.2.7.7 1.3 1.5 1 1.3-.2 2-2.6 2.1-3.9.3.2.6.2 1 .1-.6-2.2 0-6.1-.3-7.9-.1-.4-1-.5-1.7-.5h-.4zm-21.5 12c.4 0 .7.3 1 1.1.2 1.3-.3 2.6-.9 2.8-.2 0-.7 0-1-1.2v-.4c0-1.3.4-2 1-2.2zm-5.2 1c.3 0 .6.2.6.5.2.6-.3 1.3-1.2 2-.3-1.4.1-2.3.6-2.5zm18-.4c-.5.2-1-.4-1.2-1.2-.2-1 0-2.1.7-2.5v.5c.2.7.6 1.5 1.3 1.9 0 .7-.2 1.2-.7 1.3zm10-1.6c0 .5.4.7 1 .6.8-.2 1-1 .8-1.6 0-.5-.4-1-1-.8-.5.1-1 .9-.8 1.8zm-14.3-5.5c0-.4-.5-.7-1-.5-.8.2-1 1-.9 1.5.2.6.5 1 1 .8.5 0 1.1-1 1-1.8z" fill="#fff" fill-opacity=".6"/>'.$this->addElementToGhost().'</svg>';
    }

    private function addElementToGhost(): string
    {
        if (!isset(self::GHOST_ADDONS[date('m-d')])) {
            return '';
        }

        return '<path d="'.self::GHOST_ADDONS[date('m-d')].'" fill="#fff" fill-opacity="0.6"></path>';
    }
}
