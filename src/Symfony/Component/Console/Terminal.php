<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

class Terminal
{
    private static $width;
    private static $height;
    private static $stty;

    private static $windowSizeChangeSignalHandlerInstalled = false;
    private static $windowResizeListeners = [];

    /**
     * Gets the terminal width.
     *
     * @return int
     */
    public function getWidth()
    {
        self::updateDimensions();

        return self::$width;
    }

    /**
     * Gets the terminal height.
     *
     * @return int
     */
    public function getHeight()
    {
        self::updateDimensions();

        return self::$height;
    }

    /**
     * @return int[]
     */
    public function getDimensions(): array
    {
        self::updateDimensions();

        return [
            self::$width,
            self::$height,
        ];
    }

    /**
     * Sets up a handler for the SIGWINCH process signal to detect resizes of the terminal window.
     *
     * See: https://www.gnu.org/software/libc/manual/html_node/Miscellaneous-Signals.html
     *
     * todo: Any chance to support Windows here?
     *
     * @param bool $overwrite Whether to overwrite a potentially already existing handler for the SIGWINCH signal.
     * @param bool $activateAsyncSignalHandling Whether to also activate asynchronous signal processing to ensure immediate event processing.
     * @return bool Returns true if the signal handler has been successfully installed and false otherwise.
     */
    public static function installWindowResizeSignalHandler(bool $overwrite = false, bool $activateAsyncSignalHandling = true): bool
    {
        if (!\extension_loaded('pcntl') || !function_exists('pcntl_signal')) {
            return false;
        }

        // try to activate asynchronous signal handling
        // todo: should we fail if this cannot be set?
        if ($activateAsyncSignalHandling && \function_exists('pcntl_async_signals')) {
            \pcntl_async_signals(true);
        }

        // checking this after call to \pcntl_async_signals() to allow activation of async signal handling with already installed signal handler
        if (self::$windowSizeChangeSignalHandlerInstalled) {
            // signal handler is already installed
            return true;
        }

        if (!$overwrite && \pcntl_signal_get_handler(\SIGWINCH)) {
            // another signal handler is already installed
            return false;
        }

        self::$windowSizeChangeSignalHandlerInstalled = \pcntl_signal(\SIGWINCH, function() {
            self::updateDimensions(true);
        });

        return self::$windowSizeChangeSignalHandlerInstalled;
    }

    /**
     * Registers a listener callable that will be called when a resize of the terminal window has been detected.
     *
     * A resize might get detected when:
     * - explicitly retrieving the terminal dimensions via getWidth()/getHeight() -or-
     * - installWindowResizeSignalHandler() has been used to set up a signal handler and a corresponding process signal has been received
     *
     * @param callable $listener
     */
    public static function registerResizeListener(callable $listener): void
    {
        if (array_search($listener, self::$windowResizeListeners, true) === false) {
            self::$windowResizeListeners[] = $listener;
        }
    }

    /**
     * Removes a resize listener previously added with registerResizeListener().
     *
     * @param callable $listener
     */
    public static function unregisterResizeListener(callable $listener): void
    {
        if (($key = array_search($listener, self::$windowResizeListeners, true)) !== false) {
            unset(self::$windowResizeListeners[$key]);
        }
    }

    /**
     * @internal
     *
     * @return bool
     */
    public static function hasSttyAvailable()
    {
        if (null !== self::$stty) {
            return self::$stty;
        }

        // skip check if exec function is disabled
        if (!\function_exists('exec')) {
            return false;
        }

        exec('stty 2>&1', $output, $exitcode);

        return self::$stty = 0 === $exitcode;
    }

    private static function updateDimensions(bool $force = false)
    {
        $lastWidth = self::$width;
        $lastHeight = self::$height;

        $width = getenv('COLUMNS');
        $height = getenv('LINES');

        if ($width !== false && $height !== false) {
            self::$width = (int)trim($width);
            self::$height = (int)trim($height);
        } elseif ($force || self::$width === null || self::$height === null || !self::$windowSizeChangeSignalHandlerInstalled) {
            // only update dimensions with expensive methods when:
            // - forced to -or-
            // - no other value is available -or-
            // - we would not have been notified about a terminal resize via a signal handler
            if ('\\' === \DIRECTORY_SEPARATOR) {
                if (preg_match('/^(\d+)x(\d+)(?: \((\d+)x(\d+)\))?$/', trim(getenv('ANSICON')), $matches)) {
                    // extract [w, H] from "wxh (WxH)"
                    // or [w, h] from "wxh"
                    self::$width = (int) $matches[1];
                    self::$height = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
                } elseif (!self::hasVt100Support() && self::hasSttyAvailable()) {
                    // only use stty on Windows if the terminal does not support vt100 (e.g. Windows 7 + git-bash)
                    // testing for stty in a Windows 10 vt100-enabled console will implicitly disable vt100 support on STDOUT
                    self::initDimensionsUsingStty();
                } elseif (null !== $dimensions = self::getConsoleMode()) {
                    // extract [w, h] from "wxh"
                    self::$width = (int) $dimensions[0];
                    self::$height = (int) $dimensions[1];
                }
            } else {
                self::initDimensionsUsingStty();
            }
        }

        // guess dimensions as last resort
        self::$width = self::$width ?? 80;
        self::$height = self::$height ?? 50;

        if ($lastWidth !== self::$width || $lastHeight !== self::$height) {
            self::triggerWindowResizeListeners([self::$width, self::$height]);
        }
    }

    private static function triggerWindowResizeListeners(array $dimensions): void
    {
        foreach (self::$windowResizeListeners as $listener) {
            \call_user_func($listener, $dimensions);
        }
    }

    /**
     * Returns whether STDOUT has vt100 support (some Windows 10+ configurations).
     */
    private static function hasVt100Support(): bool
    {
        return \function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(fopen('php://stdout', 'w'));
    }

    /**
     * Initializes dimensions using the output of an stty columns line.
     */
    private static function initDimensionsUsingStty()
    {
        if ($sttyString = self::getSttyColumns()) {
            if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                // extract [w, h] from "rows h; columns w;"
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            } elseif (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                // extract [w, h] from "; h rows; w columns"
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            }
        }
    }

    /**
     * Runs and parses mode CON if it's available, suppressing any error output.
     *
     * @return int[]|null An array composed of the width and the height or null if it could not be parsed
     */
    private static function getConsoleMode(): ?array
    {
        $info = self::readFromProcess('mode CON');

        if (null === $info || !preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
            return null;
        }

        return [(int) $matches[2], (int) $matches[1]];
    }

    /**
     * Runs and parses stty -a if it's available, suppressing any error output.
     */
    private static function getSttyColumns(): ?string
    {
        return self::readFromProcess('stty -a | grep columns');
    }

    private static function readFromProcess(string $command): ?string
    {
        if (!\function_exists('proc_open')) {
            return null;
        }

        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, null, null, ['suppress_errors' => true]);
        if (!\is_resource($process)) {
            return null;
        }

        $info = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $info;
    }
}
