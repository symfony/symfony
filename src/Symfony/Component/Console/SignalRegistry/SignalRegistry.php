<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\SignalRegistry;

final class SignalRegistry
{
    /**
     * @var array<int, array<callable>>
     */
    private array $signalHandlers = [];

    /**
     * @var array<array<int, array<callable>>>
     */
    private array $stack = [];

    /**
     * @var array<int, callable|int|string>
     */
    private array $originalHandlers = [];

    /**
     * @var array<string, true>
     */
    private static array $autoloadsInProgress = [];

    private static bool $autoloaderRegistered = false;

    public function __construct()
    {
        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
    }

    public function register(int $signal, callable $signalHandler): void
    {
        self::registerAutoloader();

        $previous = pcntl_signal_get_handler($signal);

        if (!isset($this->originalHandlers[$signal])) {
            $this->originalHandlers[$signal] = $previous;
        }

        if (!isset($this->signalHandlers[$signal])) {
            if (\is_callable($previous) && [$this, 'handle'] !== $previous) {
                $this->signalHandlers[$signal][] = $previous;
            }
        }

        $this->signalHandlers[$signal][] = $signalHandler;

        pcntl_signal($signal, [$this, 'handle']);
    }

    public static function isSupported(): bool
    {
        return \function_exists('pcntl_signal');
    }

    /**
     * @internal
     */
    public function handle(int $signal): void
    {
        $count = \count($this->signalHandlers[$signal]);

        foreach ($this->signalHandlers[$signal] as $i => $signalHandler) {
            $hasNext = $i !== $count - 1;
            $signalHandler($signal, $hasNext);
        }
    }

    /**
     * Pushes the current active handlers onto the stack and clears the active list.
     *
     * This prepares the registry for a new set of handlers within a specific scope.
     *
     * @internal
     */
    public function pushCurrentHandlers(): void
    {
        // Restore the original OS-level disposition while the active map is empty,
        // so signals are not routed through handle() without a registered handler.
        foreach ($this->signalHandlers as $signal => $handlers) {
            if (isset($this->originalHandlers[$signal])) {
                pcntl_signal($signal, $this->originalHandlers[$signal]);
            }
        }

        $this->stack[] = $this->signalHandlers;
        $this->signalHandlers = [];
    }

    /**
     * Restores the previous handlers from the stack, making them active.
     *
     * This also restores the original OS-level signal handler if no
     * more handlers are registered for a signal that was just popped.
     *
     * @internal
     */
    public function popPreviousHandlers(): void
    {
        $popped = $this->signalHandlers;
        $previous = array_pop($this->stack) ?? [];

        // Expose a transitional superset so handle() never reads a missing key
        // if a signal lands while the OS-level handlers below are being swapped.
        $this->signalHandlers = $previous + $popped;

        // Reinstall the registry handler for signals owned by the restored scope.
        foreach ($previous as $signal => $handlers) {
            pcntl_signal($signal, [$this, 'handle']);
        }

        // Restore the original OS-level handler for signals no scope owns anymore.
        foreach ($popped as $signal => $handlers) {
            if (!isset($previous[$signal]) && isset($this->originalHandlers[$signal])) {
                pcntl_signal($signal, $this->originalHandlers[$signal]);
            }
        }

        $this->signalHandlers = $previous;
    }

    /**
     * @internal
     */
    public function scheduleAlarm(int $seconds): void
    {
        pcntl_alarm($seconds);
    }

    /**
     * Queues the signals that arrive while an autoload is running, and handles them when it is over.
     *
     * The engine cannot autoload a class that is already being autoloaded: a handler that needs such a class would fail with a "Class not found" error if it ran in the middle of the autoload.
     */
    private static function registerAutoloader(): void
    {
        if (self::$autoloaderRegistered) {
            return;
        }
        self::$autoloaderRegistered = true;

        spl_autoload_register(static function (string $class): void {
            if (isset(self::$autoloadsInProgress[$class])) {
                // spl_autoload_call() below invokes this autoloader again for the class being loaded
                return;
            }
            self::$autoloadsInProgress[$class] = true;
            $async = pcntl_async_signals(false);
            try {
                spl_autoload_call($class);
            } finally {
                unset(self::$autoloadsInProgress[$class]);
                pcntl_async_signals($async);
                if ($async) {
                    pcntl_signal_dispatch();
                }
            }
        }, true, true);
    }
}
