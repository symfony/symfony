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

    public function __construct()
    {
        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
    }

    public function register(int $signal, callable $signalHandler): void
    {
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
        // PHP cannot autoload a class that is already being autoloaded, so a handler that needs it would fail.
        // An alarm can be retried a second later; other signals cannot be delayed and run right away.
        if (\SIGALRM === $signal && self::isAutoloading()) {
            pcntl_alarm(1);

            return;
        }

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

    private static function isAutoloading(): bool
    {
        $autoloaders = ['spl_autoload_call' => [null]];

        foreach (spl_autoload_functions() as $autoloader) {
            if ($autoloader instanceof \Closure) {
                $r = new \ReflectionFunction($autoloader);
                $autoloader = [$r->getClosureThis() ?? $r->getClosureScopeClass()?->name, $r->name];
            } elseif (\is_object($autoloader)) {
                $autoloader = [$autoloader, '__invoke'];
            } elseif (\is_string($autoloader)) {
                $autoloader = [null, $autoloader];
            }

            // Before PHP 8.4, all closures are named "{closure}" in stack frames, so they cannot be told apart
            if (!str_ends_with($autoloader[1], '{closure}')) {
                $autoloaders[$autoloader[1]][] = $autoloader[0];
            }
        }

        foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (\in_array($frame['object'] ?? $frame['class'] ?? null, $autoloaders[$frame['function']] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
