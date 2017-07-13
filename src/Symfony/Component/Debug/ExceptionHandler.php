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
use Symfony\Component\Debug\Formatter\FormatterInterface;
use Symfony\Component\Debug\Formatter\HtmlFormatter;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

/**
 * ExceptionHandler catches exceptions and generates debug output.
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
    private $formatter;

    public function __construct($debug = true, $charset = null, $fileLinkFormat = null, FormatterInterface $formatter = null)
    {
        $this->debug = $debug;
        $this->charset = $charset ?: ini_get('default_charset') ?: 'UTF-8';
        $this->fileLinkFormat = $fileLinkFormat;

        if (!$formatter) {
            $formatter = new HtmlFormatter($fileLinkFormat);
        }
        $this->setFormatter($formatter);
    }

    /**
     * Registers the exception handler.
     *
     * @param bool               $debug          Enable/disable debug mode, where the stack trace is displayed
     * @param string|null        $charset        The charset used by exception messages
     * @param string|null        $fileLinkFormat The IDE link template used by the HtmlFormatter
     * @param FormatterInterface $formatter      The output formatter
     *
     * @return static
     */
    public static function register($debug = true, $charset = null, $fileLinkFormat = null, FormatterInterface $formatter = null)
    {
        $handler = new static($debug, $charset, $fileLinkFormat, $formatter);

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
     * @param string|FileLinkFormatter $fileLinkFormat The format for links to source files
     *
     * @return string The previous file link format
     *
     * @deprecated Deprecated since version 3.X, to be removed in 4.0. Use
     *             \Symfony\Component\Debug\Formatter\HtmlFormatter::setFileLinkFormat() instead.
     */
    public function setFileLinkFormat($fileLinkFormat)
    {
        $old = $this->fileLinkFormat;
        $this->fileLinkFormat = $fileLinkFormat;

        if ($this->formatter instanceof HtmlFormatter) {
            $this->formatter->setFileLinkFormat($format);
        }

        return $old;
    }

    /**
     * Gets the output formatter.
     *
     * @return formatterInterface the output formatter
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * Sets the output formatter.
     *
     * @param FormatterInterface $formatter the output formatter
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
        $formatter->setCharset($this->charset);
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

            header('Content-Type: '.$this->formatter->getContentType());
        }

        echo $this->getResponseContent($exception);
    }

    /**
     * Gets the formatted exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The formatted exception
     */
    public function getResponseContent($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        return $this->formatter->getContent($exception, $this->debug);
    }

    /**
     * Gets the full HTML page associated with the given exception.
     *
     * @param \Exception|FlattenException $exception An \Exception or FlattenException instance
     *
     * @return string The HTML page as a string
     *
     * @deprecated Deprecated since version 3.X, to be removed in 4.0. Use
     *             \Symfony\Component\Debug\Formatter\HtmlFormatter::getContent() instead.
     */
    public function getHtml($exception)
    {
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::create($exception);
        }

        $formatter = $this->formatter instanceof HtmlFormatter ? $this->formatter : new HtmlFormatter($this->fileLinkFormat);

        return $formatter->getContent($exception, $this->debug);
    }

    /**
     * Gets the HTML body content associated with the given exception.
     *
     * @param FlattenException $exception A FlattenException instance
     *
     * @return string The HTML body as a string
     */
    public function getContent(FlattenException $exception)
    {
        $formatter = $this->formatter instanceof HtmlFormatter ? $this->formatter : new HtmlFormatter($this->fileLinkFormat);

        echo $this->formatter->getBody($exception, $this->debug);
    }

    /**
     * Gets the stylesheet associated with the given exception.
     *
     * @param FlattenException $exception A FlattenException instance
     *
     * @return string The stylesheet as a string
     *
     * @deprecated Deprecated since version 3.X, to be removed in 4.0. Use
     *             \Symfony\Component\Debug\Formatter\HtmlFormatter::getStyleSheet() instead.
     */
    public function getStylesheet(FlattenException $exception)
    {
        $formatter = $this->formatter instanceof HtmlFormatter ? $this->formatter : new HtmlFormatter($this->fileLinkFormat);

        return $formatter->getContent($exception);
    }
}
