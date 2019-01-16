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
 */
class ExceptionHandler
{
    private $debug;
    private $charset;
    private $handler;
    private $caughtBuffer;
    private $caughtLength;
    private $fileLinkFormat;

    public function __construct($debug = true, $charset = null, $fileLinkFormat = null)
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
            \call_user_func($this->handler, $exception);
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
                $title = 'Whoops, looks like something went wrong.';
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
                        $content .= sprintf('at <span class="trace-class">%s</span><span class="trace-type">%s</span><span class="trace-method">%s</span>(<span class="trace-arguments">%s</span>)', $this->formatClass($trace['class']), $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
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
                $title = sprintf('Exception thrown when handling an exception (%s: %s)', \get_class($e), $this->escapeHtml($e->getMessage()));
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

    private function decorate($content, $css)
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

    private function formatClass($class)
    {
        $parts = explode('\\', $class);

        return sprintf('<abbr title="%s">%s</abbr>', $class, array_pop($parts));
    }

    private function formatPath($path, $line)
    {
        $file = $this->escapeHtml(preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path);
        $fmt = $this->fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');

        if (!$fmt) {
            return sprintf('<span class="block trace-file-path">in <a title="%s%3$s"><strong>%s</strong>%s</a></span>', $this->escapeHtml($path), $file, 0 < $line ? ' line '.$line : '');
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
            $link = $fmt->format($path, $line);
        }

        return sprintf('<span class="block trace-file-path">in <a href="%s" title="Go to source"><strong>%s</string>%s</a></span>', $this->escapeHtml($link), $file, 0 < $line ? ' line '.$line : '');
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
    private function escapeHtml($str)
    {
        return htmlspecialchars($str, ENT_COMPAT | ENT_SUBSTITUTE, $this->charset);
    }

    private function getSymfonyGhostAsSvg()
    {
        return '<svg viewBox="0 0 136 81" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path d="M92.358 20.403a23.168 23.168 0 0 1 9.003 1.881 23.67 23.67 0 0 1 5.197 3.079 24.257 24.257 0 0 1 3.457 3.296 24.771 24.771 0 0 1 5.042 9.396c.486 1.72.78 3.492.895 5.28l.008.142.028.158.015.246v13.875c.116.034.232.065.348.098.193.054.383.116.577.168.487.125.989.191 1.49.215.338.016.689.023 1.021-.059.021-.005.032-.029.048-.044l.095-.1c.243-.265.461-.552.663-.851.277-.408.523-.837.746-1.279l.042-.087c-.066-.012-.131-.026-.197-.04l-.099-.023a5.536 5.536 0 0 1-.694-.242 5.649 5.649 0 0 1-2.374-1.845 5.694 5.694 0 0 1-.824-1.594 6.514 6.514 0 0 1-.267-2.781c.045-.394.126-.779.233-1.159.079-.278.162-.562.307-.812.094-.163.129-.196.247-.341l.79-.882c.143-.143.174-.186.34-.303.249-.174.536-.289.834-.333.074-.011.15-.014.224-.02l1.188-.037c.173.004.217-.002.388.028s.211.05.375.105l.018.007c.059.026.119.05.176.079.151.076.179.104.313.2l.006-.021c.073-.187.084-.238.187-.41.077-.129.167-.249.27-.357.051-.054.108-.103.162-.154l1.124-.95c.14-.107.172-.14.327-.224.155-.085.199-.094.363-.154l.019-.006c.169-.043.211-.06.385-.077.174-.016.218-.007.392.003l1.446.158c.193.033.244.033.43.098.278.097.534.259.744.47.053.053.1.112.149.167l.923 1.158.149.213.028.054.017-.014.184-.125c.196-.104.196-.104.402-.184l1.386-.451c.064-.018.126-.038.19-.052.129-.028.259-.042.39-.043.16-.002.321.017.478.047.364.069.711.21 1.032.396.162.094.316.199.469.308.088.063.176.132.27.188l.021.011c.19.123.245.146.409.305.185.178.336.393.443.63.035.079.061.162.091.243l.439 1.428c.045.175.062.219.081.4.02.193.006.381-.015.573a7.79 7.79 0 0 1-.101.645c-.09.455-.212.901-.365 1.339-.128.366-.273.73-.445 1.077-.658 1.335-1.652 2.512-2.917 3.265a6.399 6.399 0 0 1-1.019.489 6.097 6.097 0 0 1-.631.203c-.226.058-.455.1-.686.134l-.096.012-.061.007c-.01.176-.022.352-.036.528-.034.39-.082.778-.153 1.163a14.258 14.258 0 0 1-.574 2.114c-.229.654-.484 1.306-.806 1.918a9.16 9.16 0 0 1-.386.656c-.219.348-.451.686-.697 1.013-.448.594-.946 1.148-1.521 1.614-.255.207-.52.397-.808.553-.9.489-1.919.648-2.921.735-.493.038-.986.059-1.478.099-.162.015-.324.033-.486.049-.145.011-.289.022-.434.03a15.768 15.768 0 0 1-2.778-.118c0 1.416.007 2.832-.001 4.248a9.737 9.737 0 0 1-.684 3.479 9.615 9.615 0 0 1-1.72 2.804 9.326 9.326 0 0 1-3.04 2.279 9.046 9.046 0 0 1-5.33.715 9.064 9.064 0 0 1-2.988-1.079 9.363 9.363 0 0 1-2.761-2.429 10.078 10.078 0 0 1-1.05 1.16 9.281 9.281 0 0 1-1.871 1.358 9.033 9.033 0 0 1-2.495.926 9.04 9.04 0 0 1-6.462-1.072 9.395 9.395 0 0 1-2.602-2.292l-.062-.08a10.896 10.896 0 0 1-.53.635 9.266 9.266 0 0 1-2.671 2.032 9.028 9.028 0 0 1-6.044.751 9.048 9.048 0 0 1-2.436-.934 9.343 9.343 0 0 1-2.286-1.803 9.572 9.572 0 0 1-1.783-2.757 9.705 9.705 0 0 1-.773-3.693V67.244c-.157.024-.314.047-.472.067-.487.06-.977.103-1.469.109-.313.004-.627-.009-.94-.028-.426-.025-.85-.065-1.273-.125-1.833-.264-3.65-.92-5.109-2.117a8.172 8.172 0 0 1-1.064-1.049 10.155 10.155 0 0 1-.878-1.236 15.277 15.277 0 0 1-.7-1.274 20.835 20.835 0 0 1-1.889-6.194l-.018-.142-.008-.061a6.47 6.47 0 0 1-.99-.297 6.135 6.135 0 0 1-.61-.285 6.587 6.587 0 0 1-.889-.562c-1.228-.924-2.124-2.259-2.668-3.711a9.947 9.947 0 0 1-.307-.99 10.288 10.288 0 0 1-.318-1.923c-.009-.147-.011-.293-.015-.44v-.037c.008-.175.004-.22.037-.393.033-.173.053-.213.11-.378l.561-1.417c.031-.068.06-.139.095-.206a2.028 2.028 0 0 1 .771-.803c.093-.054.194-.095.289-.145l.311-.179c.352-.194.714-.358 1.107-.44.213-.044.426-.061.643-.061l.034.001c.177.014.223.01.396.052.174.041.214.065.379.132l1.347.635c.073.04.15.076.221.121.142.091.272.2.388.325.154.166.176.222.297.414l.022.047.722-.762.168-.158c.165-.122.202-.161.385-.253.206-.102.429-.168.656-.193.076-.008.152-.008.228-.011l1.46.013c.177.011.223.007.397.046.175.038.215.061.381.126l.018.008c.154.08.196.094.338.196.142.102.169.137.294.259l.853.912.152-.067.191-.063.019-.005.196-.042c.177-.019.222-.031.401-.022.066.003.133.013.199.02l1.185.182c.073.016.147.027.219.047.288.08.558.227.784.428.151.135.177.181.303.339l.714 1.004c.097.152.127.187.201.352.077.172.123.352.164.536.029.134.056.269.08.404.063.361.102.725.112 1.091.021.78-.08 1.566-.321 2.307a5.906 5.906 0 0 1-.532 1.183 5.463 5.463 0 0 1-3.257 2.489l-.03.008c.195.584.433 1.155.712 1.701.215.422.453.833.735 1.211.026.035.026.034.053.068l.058.072c.056.024.113.042.171.06.319.09.653.121.982.14.488.027.978.013 1.461-.06.167-.028.333-.062.499-.089.134-.022.267-.042.401-.066l.28-.056c.154-.023.308-.049.462-.076l.115-.021V43.881c.011-.203.006-.203.042-.404a26.66 26.66 0 0 1 .226-2.241 24.737 24.737 0 0 1 5.72-12.577 24.204 24.204 0 0 1 3.457-3.296 23.653 23.653 0 0 1 4.937-2.966 23.215 23.215 0 0 1 5.604-1.681 23.703 23.703 0 0 1 3.958-.313zm-.287 2.042a21.169 21.169 0 0 0-8.012 1.622 21.636 21.636 0 0 0-4.799 2.766 22.233 22.233 0 0 0-3.205 2.985 22.705 22.705 0 0 0-4.897 9.196 23.383 23.383 0 0 0-.737 4.867h-.025v15.744c-.258.053-.258.052-.517.101-.28.051-.56.1-.841.144-.211.04-.421.079-.632.115l-.232.037-.411.078c-.116.02-.233.035-.348.057-.305.056-.609.11-.917.14a9.929 9.929 0 0 1-1.883-.017c-.514-.056-1.044-.155-1.51-.397a1.762 1.762 0 0 1-.33-.218 1.925 1.925 0 0 1-.234-.252 5.248 5.248 0 0 1-.174-.22 8.97 8.97 0 0 1-.582-.883 13.806 13.806 0 0 1-.941-1.971 14.348 14.348 0 0 1-.608-1.954 14.04 14.04 0 0 1-.169-.86l-.015-.11-.015-.109c.161-.007.16-.007.321-.016a12.793 12.793 0 0 0 1.413-.182 4.43 4.43 0 0 0 .28-.074 3.56 3.56 0 0 0 1.199-.616c.309-.244.576-.543.786-.88.163-.261.292-.544.387-.838.123-.378.192-.774.214-1.172a5.102 5.102 0 0 0-.024-.865 7.192 7.192 0 0 0-.145-.799l-.714-1.005-1.184-.182-.019.005-.946.758-.12 1.229a4.953 4.953 0 0 1 .111.455c.032.181.052.36.043.544a1.04 1.04 0 0 1-.056.303c-.11.301-.419.451-.696.548-.402.142-.813.25-1.229.339l.07-.648c.022-.191.047-.381.08-.57.036-.207.079-.413.152-.61.077-.211.182-.412.296-.605.044-.074.092-.146.135-.222.029-.048.031-.047.055-.098.016-.033.031-.064.045-.098l-.026-1.551-1.042-1.116-.018-.008-1.459-.014-1.022 1.079c-.049.128-.08.258-.111.393a5.274 5.274 0 0 0-.1.651 5.55 5.55 0 0 0-.031.466c-.009.687.104 1.37.294 2.028.11.382.262.753.402 1.123-.115-.029-.228-.06-.342-.092a9.526 9.526 0 0 1-1.176-.446c-.108-.05-.111-.048-.191-.097a1.921 1.921 0 0 1-.327-.249c-.416-.4-.589-.986-.671-1.55a5.643 5.643 0 0 1-.057-.549c-.007-.143-.006-.286-.007-.429-.001-.186.005-.372.011-.558l.001-.039-.567-1.446-1.347-.634c-.316-.008-.599.144-.867.299-.109.063-.218.126-.33.185a2.058 2.058 0 0 1-.125.061l-.042.019-.561 1.416c0 .209.014.416.036.624.04.377.106.75.196 1.118.076.309.164.616.275.913.415 1.109 1.093 2.146 2.043 2.838.234.171.485.317.746.442.183.088.371.161.565.22.263.079.532.13.803.17.296.045.594.075.892.095l.108.007c.004.151.01.302.017.453.011.177.023.353.038.529a18.13 18.13 0 0 0 .762 3.752c.239.76.522 1.505.857 2.225.23.494.483.977.767 1.44.288.469.608.915.989 1.308 1.001 1.028 2.324 1.648 3.687 1.976.643.155 1.298.243 1.955.287.311.021.622.036.933.033.418-.006.835-.041 1.25-.094.238-.03.477-.064.713-.11.117-.023.232-.053.348-.081.196-.048.392-.097.586-.151.147-.041.291-.094.436-.144.204-.069.408-.139.608-.217l.006-.003c0 2.207-.013 4.414.001 6.62a7.942 7.942 0 0 0 .13 1.32 7.545 7.545 0 0 0 2.383 4.243 7.23 7.23 0 0 0 2.258 1.372 7.094 7.094 0 0 0 7.012-1.164 7.504 7.504 0 0 0 2.035-2.613 7.727 7.727 0 0 0 .676-2.401l.009-.088.038-.765a8.16 8.16 0 0 0 .113 1.324c.121.694.338 1.37.643 2.001a7.49 7.49 0 0 0 1.692 2.275 7.266 7.266 0 0 0 2.24 1.399 7.11 7.11 0 0 0 4.615.19 7.212 7.212 0 0 0 2.351-1.218 7.501 7.501 0 0 0 2.128-2.64 7.763 7.763 0 0 0 .702-2.39l.01-.088.009-.088.038-.765a9.339 9.339 0 0 0 .021.575 7.626 7.626 0 0 0 .621 2.504 7.507 7.507 0 0 0 2.35 2.972 7.1 7.1 0 0 0 7.026.881 7.275 7.275 0 0 0 2.268-1.515 7.525 7.525 0 0 0 1.612-2.338 7.58 7.58 0 0 0 .572-2.033c.048-.347.069-.696.071-1.046v-6.721c.136.051.271.101.408.148a12.153 12.153 0 0 0 1.976.443c.264.035.529.055.794.071.33.02.66.031.991.027.245-.002.49-.012.735-.031.245-.018.49-.048.735-.068.407-.03.814-.051 1.221-.079a9.493 9.493 0 0 0 1.384-.188c.315-.073.626-.174.912-.329a3.53 3.53 0 0 0 .586-.418c.46-.386.85-.85 1.205-1.337a12.178 12.178 0 0 0 .801-1.246c.122-.232.229-.471.33-.712a15.873 15.873 0 0 0 .681-1.988c.136-.525.23-1.058.282-1.598.035-.41.052-.822.088-1.232.03-.317.078-.632.121-.947l.018-.145.016-.145c.144.009.287.016.431.021.459.009.924.007 1.378-.07a4.456 4.456 0 0 0 1.353-.482c.989-.55 1.752-1.466 2.258-2.488.116-.235.214-.48.304-.727a7.58 7.58 0 0 0 .377-1.43c.016-.109.027-.218.039-.328l.001-.009-.438-1.428a5.206 5.206 0 0 1-.16-.096c-.158-.105-.311-.219-.467-.326a3.829 3.829 0 0 0-.159-.1 1.356 1.356 0 0 0-.509-.18l-.01-.001-1.386.452-.681 1.323c-.016.212-.023.424-.043.636a5.66 5.66 0 0 1-.139.873c-.118.494-.316.999-.702 1.338a1.865 1.865 0 0 1-.496.301l-.272.087a9.57 9.57 0 0 1-.83.205 8.797 8.797 0 0 1-.582.091l.229-.462c.079-.163.158-.325.229-.492.051-.118.096-.239.139-.36.036-.103.076-.209.103-.315.019-.075.031-.153.041-.229.017-.132.031-.263.043-.395.035-.368.06-.737.094-1.104.02-.187.048-.372.067-.559.015-.167.015-.336.012-.505a4.76 4.76 0 0 0-.074-.826c-.012-.065-.03-.13-.045-.194l-.003-.009-.923-1.157-1.446-.159-.019.006-1.124.95-.154 1.489c.011.034.024.066.037.099.044.115.107.221.161.331.046.096.088.193.13.29l.031.076c.013.033.017.07.023.105.012.096.022.191.031.287.031.364.047.73.081 1.093.013.102.028.202.04.303.014.145.027.29.033.435.014.28.016.561.023.841a9.588 9.588 0 0 1-.862-.323c-.063-.027-.128-.062-.193-.084a1.325 1.325 0 0 0-.067-.013c-.081-.01-.162-.017-.243-.025-.245-.02-.49-.037-.734-.061-.066-.007-.132-.014-.198-.028l-.017-.005c-.03-.013-.029-.014-.067-.038a1.614 1.614 0 0 1-.161-.108.863.863 0 0 1-.22-.242c-.089-.155-.102-.34-.09-.517.02-.299.117-.591.228-.866l.004-.009-.018-1.197-.874-.84-.018-.007-1.188.036-.79.882c-.037.112-.074.224-.106.338a4.756 4.756 0 0 0-.171 1.906c.039.329.115.654.233.963a3.542 3.542 0 0 0 1.263 1.636c.313.222.659.393 1.019.517.237.082.487.111.734.145.479.06.959.106 1.438.166.121.017.241.037.362.058l.158.026a12.12 12.12 0 0 1-.923 2.565 13.221 13.221 0 0 1-.829 1.474 9.474 9.474 0 0 1-.984 1.286c-.08.087-.163.17-.248.252a1.655 1.655 0 0 1-.329.262 2.376 2.376 0 0 1-.722.247c-.457.089-.927.093-1.39.071-.391-.018-.781-.06-1.168-.123a7.817 7.817 0 0 1-.609-.124c-.226-.056-.448-.124-.671-.191-.065-.019-.131-.035-.197-.054a14.75 14.75 0 0 1-.543-.165 23.384 23.384 0 0 1-.453-.128c-.196-.059-.195-.059-.39-.12l-.276-.077V43.881h-.025a34.633 34.633 0 0 0-.031-.557 23.606 23.606 0 0 0-.4-2.994 22.743 22.743 0 0 0-1.492-4.708 22.567 22.567 0 0 0-4.593-6.748 21.865 21.865 0 0 0-6.882-4.706 21.175 21.175 0 0 0-8.115-1.722l-.411-.001zm9.15 33.69c.109.015.214.038.315.085a1.012 1.012 0 0 1 .574.771c.021.132.013.268.009.4a8.38 8.38 0 0 1-.026.476 8.767 8.767 0 0 1-1.564 4.282c-.306.437-.65.846-1.024 1.222a10.09 10.09 0 0 1-4.612 2.627c-1.32.343-2.704.427-4.055.254a10.422 10.422 0 0 1-2.67-.709 9.917 9.917 0 0 1-3.57-2.503 9.312 9.312 0 0 1-.775-.984 8.933 8.933 0 0 1-.731-1.288 8.648 8.648 0 0 1-.795-3.377c-.003-.104-.008-.211 0-.316a1.042 1.042 0 0 1 .254-.609.98.98 0 0 1 1.337-.125 1.023 1.023 0 0 1 .385.719c.007.151.006.303.014.454a6.547 6.547 0 0 0 .524 2.217c.257.595.599 1.15 1.006 1.648.325.398.691.759 1.087 1.081.312.253.642.482.987.684 2.592 1.522 5.945 1.538 8.553.047a7.982 7.982 0 0 0 1.069-.731 7.619 7.619 0 0 0 1.142-1.15 6.949 6.949 0 0 0 1.018-1.741 6.538 6.538 0 0 0 .467-2.425l.004-.084a1.012 1.012 0 0 1 .672-.876c.08-.028.158-.04.241-.05.082-.003.082-.003.164.001zm-70.51-12.426c-15.5.93-28.544-5.922-30.126-16.443C-1.156 15.689 11.64 4.024 29.14 1.235c17.501-2.79 33.123 4.345 34.864 15.922 1.575 10.475-8.749 21.021-23.691 25.001l.001.099a31.185 31.185 0 0 0 .042.833c.007.094.019.188.021.282.006.178.013.356.024.534.011.16.024.32.039.48.017.154.038.306.058.459.036.273.077.544.144.811a4.723 4.723 0 0 0 .449 1.128c.192.332.434.628.702.898l.047.05c.151.139.302.275.461.403.24.192.492.367.748.537.474.314.962.6 1.457.877l.041.023.588.735-.729.586c-.376.112-.755.216-1.135.309a11.193 11.193 0 0 1-2.562.355 8.575 8.575 0 0 1-2.995-.486 8.461 8.461 0 0 1-.96-.413 11.194 11.194 0 0 1-1.836-1.152 13.345 13.345 0 0 1-1.07-.934c-.23-.221-.454-.448-.672-.681-.121-.129-.246-.258-.36-.395a23.448 23.448 0 0 1-1.328-1.773c-.051-.076-.049-.077-.095-.155l-.277-.477-.072-.13c-.081-.177-.159-.357-.238-.535l-.003-.01-.092-.707zm52.409-7.804c3.557 0 6.444 3.201 6.444 7.145 0 3.944-2.887 7.146-6.444 7.146s-6.444-3.202-6.444-7.146 2.887-7.145 6.444-7.145zm18.062 0c3.557 0 6.444 3.201 6.444 7.145 0 3.944-2.887 7.146-6.444 7.146s-6.444-3.202-6.444-7.146 2.887-7.145 6.444-7.145zM83.12 42.029c1.915 0 3.47 1.601 3.47 3.573s-1.555 3.573-3.47 3.573c-1.915 0-3.47-1.601-3.47-3.573s1.555-3.573 3.47-3.573zm17.846 0c1.915 0 3.47 1.601 3.47 3.573s-1.555 3.573-3.47 3.573c-1.915 0-3.47-1.601-3.47-3.573s1.555-3.573 3.47-3.573zM17.019 28c-.368 1.65-1.848 5.008-5.178 5.799-2.572.611-4.153-.815-4.544-2.559-.424-1.891.722-3.532 2.121-4.575a3.473 3.473 0 0 1-1.446-2.099c-.421-1.875.867-3.637 3.184-4.187 1.917-.455 3.185.248 3.462 1.482.265 1.184-.534 2.275-1.828 2.582-.878.209-1.574-.042-1.718-.683a1.4 1.4 0 0 1 .044-.704s.287.227.894.083c.751-.179 1.086-.709.972-1.219-.14-.625-.892-.827-1.739-.626-1.054.25-2.06 1.096-1.713 2.642.232 1.036.871 1.56 1.483 1.813.245-.11.481-.183.688-.233.943-.224 1.48-.005 1.587.472.092.411-.144.935-1.166 1.178a3.255 3.255 0 0 1-1.548.004c-.837.771-1.58 1.883-1.27 3.264.276 1.234 1.267 2.125 2.944 1.726 2.598-.617 3.861-3.638 4.277-4.883-.353-.574-.615-1.153-.732-1.676-.107-.477.145-1.005.863-1.175.48-.114.702.127.846.769a2.77 2.77 0 0 1-.03.995c.209.331.443.622.735.951.616-1.983 1.369-3.877 1.737-3.964.591-.141 1.492.65 1.492.65-.815.644-1.689 2.376-2.333 4.158.804.658 1.627 1.103 2.139.982.43-.102.735-.577.95-1.151-.323-2.226.975-4.331 2.31-4.648.703-.167 1.257.204 1.39.796.114.51-.044 1.379-.854 1.745-.236-1.053-.672-1.348-.944-1.283-.495.117-.844 1.413-.538 2.778.232 1.037.712 1.529 1.351 1.377.756-.179 1.333-1.176 1.699-2.128-.265-2.095.877-4.166 2.221-4.486.671-.159 1.214.162 1.391.952.332 1.48-.986 2.885-2.173 3.444.265.734.673 1.053 1.281.909.96-.229 1.578-1.465 1.923-2.506-.125-1.267-.26-2.385-.406-3.035l-.055-.247s1.568-.286 1.778.652l.019.082c.238-.663.67-1.216 1.309-1.368.83-.197 1.526.504 1.755 1.524.497 2.22-.556 4.428-1.834 4.732-.368.087-.642.066-.883-.033.121 1.288.292 2.651.542 3.77.126.559.272 1.061.448 1.47-.464.11-1.797.392-1.978-.414-.16-.716-.342-3.206-.554-5.612-.504 1.107-1.311 2.192-2.441 2.46-1.008.24-1.685-.303-2.055-1.182-.491 1.082-1.281 2.148-2.381 2.409-.817.194-1.554-.117-1.988-1.013-.36.843-.875 1.555-1.54 1.713-.639.152-1.53-.295-2.4-1.024-.239.888-.384 1.668-.39 2.241 0 0-.701.028-.804-.433-.096-.427.065-1.436.341-2.61a10.315 10.315 0 0 1-.713-.848zm38.163-17.803c.068.157.185.527.266.889.424 1.892.37 4.451.739 6.42-.065.61-.387 3.077-1.352 3.307-.192.045-.333-.06-.422-.454-.14-.626-.091-1.607-.293-2.512-.258-1.152-.782-1.686-1.517-1.511-.767.182-1.287 1.016-1.643 2.054-.022-.099-.053-.386-.093-.567-.211-.938-1.779-.652-1.779-.652a6.2 6.2 0 0 1 .457 1.364c.07.31.119.618.155.921-.246.495-.637.996-1.225 1.135-.064.015-.128.031-.195.029a6.977 6.977 0 0 0-.126-.784c-.258-1.152-.871-2.011-1.526-1.855a.712.712 0 0 0-.423.291c-1.337.317-2.358 2.107-2.118 3.919-.214.889-.551 1.757-1.059 1.877-.415.099-.724-.452-1.03-1.817-.059-.263-.09-.706-.122-1.149.142-.64.177-1.237.081-1.665-.107-.477-.417-.733-.816-.638-.715.17-.909 1.75-.52 3.801-.238.92-.639 1.915-1.278 2.067-.464.11-.835-.27-1.012-1.059-.158-.708-.196-1.929-.236-3.08 1.201-.424 1.911-1.009 1.775-1.617-.114-.51-.739-.743-.739-.743s-.124.722-1.064 1.258c-.029-.582-.064-1.111-.137-1.44-.137-.609-.458-.914-1.688-.622.158.327.274.698.359 1.076.103.46.162.949.189 1.445-.611.128-.947.052-.947.052s-.1.457-.041.72c.078.345.432.348 1.026.224.02 1.364-.067 2.701.143 3.639.306 1.365 1.231 1.89 2.046 1.697.907-.216 1.539-1.275 1.914-2.36.407 1.245 1.031 1.955 1.951 1.736.731-.174 1.261-1.142 1.587-2.195.431.765 1.15 1.129 1.983.931 1.214-.289 1.742-1.54 1.835-2.775 0 0 .147-.018.243-.04.526-.125.949-.488 1.26-.915.04.788.053 1.518.194 2.146.111.493.339.612.595.552.495-.118 1.081-.881 1.081-.881a3.93 3.93 0 0 1-.383-1.035c-.284-1.267.317-3.541.988-3.7.208-.049.377.257.492.767.057.255.092.504.115.751l.098 1.469c.024.246.059.496.116.751.158.707.63 1.236 1.381 1.058 1.317-.313 2.07-2.634 2.178-3.956.228.157.536.175.909.086-.505-2.253.089-6.136-.298-7.864-.1-.444-1.001-.58-1.607-.583l-.467.037zM33.729 22.293c.415-.099.711.246.885 1.02.287 1.283-.222 2.616-.797 2.753-.191.045-.695-.025-.961-1.21-.025-.115-.051-.23-.061-.349.05-1.277.439-2.097.934-2.214zm-5.187.955c.271-.065.511.104.588.449.137.609-.338 1.345-1.275 1.966-.255-1.36.159-2.29.687-2.415zm18.032-.403c-.607.144-1.062-.458-1.239-1.248-.217-.97.001-2.097.644-2.457.001.155.038.32.075.484.147.658.554 1.497 1.268 1.83-.017.749-.253 1.273-.748 1.391zm9.877-1.654c.103.461.496.714 1.039.585.799-.19.973-.993.847-1.553-.125-.559-.461-.93-.988-.805-.543.13-1.108.836-.898 1.773zm-14.21-5.442c-.104-.461-.497-.714-1.056-.581-.783.186-.972.993-.847 1.552.126.56.461.93.908.824.56-.133 1.172-1.006.995-1.795z" fill="#fff" fill-opacity=".6"></path></svg>';
    }
}
