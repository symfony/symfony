<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Terminal;

use Revolt\EventLoop;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Tui\Input\StdinBuffer;

/**
 * Real terminal implementation using stdin/stdout.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Terminal implements TerminalInterface
{
    use TerminalEscapeTrait;

    private ?StdinBuffer $stdinBuffer = null;

    private string $initialSttyState = '';
    private ?\Io\Terminal\Terminal $nativeTerminal = null;
    private ?\Io\Terminal\ModeToken $nativeMode = null;
    private bool $kittyProtocolActive = false;
    private bool $started = false;
    private ?string $stdinCallbackId = null;
    private ?string $signalCallbackId = null;
    private ?string $escapeFlushCallbackId = null;
    private ?int $pendingHighSurrogate = null;

    /** @var (\Closure(string): void)|null */
    private ?\Closure $onInput = null;

    /** @var (\Closure(): void)|null */
    private ?\Closure $onResize = null;

    /** @var (\Closure(): void)|null */
    private ?\Closure $onKittyProtocolActivated = null;

    // Cached terminal dimensions (refreshed on SIGWINCH)
    private ?int $cachedColumns = null;
    private ?int $cachedRows = null;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher = new EventDispatcher(),
    ) {
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function start(callable $onInput, callable $onResize, callable $onKittyProtocolActivated): void
    {
        if ($this->started) {
            return;
        }

        $this->onInput = $onInput(...);
        $this->onResize = $onResize(...);
        $this->onKittyProtocolActivated = $onKittyProtocolActivated(...);
        $this->started = true;

        // Save initial terminal state and enable raw mode.
        //
        // Raw mode is equivalent to cfmakeraw(), matching Node.js setRawMode(true) used by the
        // Pi reference implementation. It disables canonical mode, echo, signal interpretation,
        // and extended input processing so that ALL key combinations (including Ctrl+C, Ctrl+Z,
        // Alt+Backspace) are delivered as raw bytes to the application rather than being
        // intercepted by the kernel.
        if (null !== ($native = self::createNativeTerminal()) && false !== $mode = $native->enableRawMode()) {
            $this->nativeTerminal = $native;
            $this->nativeMode = $mode;
        } elseif ($this->hasSttyAvailable()) {
            $this->initialSttyState = (string) shell_exec('stty -g');
            shell_exec('stty raw -echo');
        }

        // Set up stdin buffer for proper sequence parsing - must be done
        // BEFORE sending any queries so responses can be captured
        $this->setupStdinBuffer();

        // Enable bracketed paste mode
        $this->write("\x1b[?2004h");

        // Set up signal handlers for resize using Revolt's event loop
        if (\defined('SIGWINCH')) {
            $this->signalCallbackId = EventLoop::onSignal(\SIGWINCH, function (): void {
                // Clear cached dimensions so they get re-read
                $this->cachedColumns = null;
                $this->cachedRows = null;

                if (null !== $this->onResize) {
                    ($this->onResize)();
                }
            });
        }

        $nativeInput = null !== $this->nativeTerminal && self::supportsNativeInput();

        // Native Windows console records already carry key/modifier/release data.
        // Do not ask the terminal to encode the same information as VT input.
        if (!$nativeInput || '\\' !== \DIRECTORY_SEPARATOR) {
            // Query for Kitty keyboard protocol support. If supported, the reply
            // is handled by setupStdinBuffer().
            $this->write("\x1b[?u");
        }

        if ($nativeInput) {
            // Keep the existing event-loop architecture on every platform:
            // readability wakes the loop, then readEvent(0) drains the native
            // queue without introducing a permanent polling timer.
            $this->stdinCallbackId = EventLoop::onReadable(\STDIN, function (): void {
                $this->drainNativeInput();
            });
        } else {
            // Keep the existing userland fallback for ext-terminal 1.0.x or when
            // the extension is missing/unavailable.
            $this->stdinCallbackId = EventLoop::onReadable(\STDIN, function (): void {
                $data = fread(\STDIN, 4096);
                if (false !== $data && '' !== $data && null !== $this->stdinBuffer) {
                    $this->stdinBuffer->process($data);
                    // fread returns terminal input in complete reads in this legacy
                    // path, so a pending lone ESC can be emitted immediately.
                    $this->stdinBuffer?->flush();
                }
            });
        }
    }

    public function stop(): void
    {
        if (!$this->started) {
            return;
        }
        $this->started = false;

        // Cancel STDIN watcher
        if (null !== $this->stdinCallbackId) {
            EventLoop::cancel($this->stdinCallbackId);
            $this->stdinCallbackId = null;
        }

        // Cancel signal watcher
        if (null !== $this->signalCallbackId) {
            EventLoop::cancel($this->signalCallbackId);
            $this->signalCallbackId = null;
        }

        if (null !== $this->escapeFlushCallbackId) {
            EventLoop::cancel($this->escapeFlushCallbackId);
            $this->escapeFlushCallbackId = null;
        }
        $this->pendingHighSurrogate = null;

        // Disable bracketed paste mode
        $this->write("\x1b[?2004l");

        // Disable Kitty keyboard protocol if we enabled it
        if ($this->kittyProtocolActive) {
            $this->write("\x1b[<u");
            $this->kittyProtocolActive = false;
        }

        // Clear stdin buffer
        if (null !== $this->stdinBuffer) {
            $this->stdinBuffer->clear();
            $this->stdinBuffer = null;
        }

        // Restore terminal state
        if (null !== $this->nativeTerminal) {
            $this->nativeTerminal->restoreMode($this->nativeMode);
            $this->nativeTerminal = null;
            $this->nativeMode = null;
        } elseif ('' !== $this->initialSttyState) {
            shell_exec('stty '.escapeshellarg(trim($this->initialSttyState)));
        }

        $this->onInput = null;
        $this->onResize = null;
        $this->onKittyProtocolActivated = null;
    }

    public function write(string $data): void
    {
        fwrite(\STDOUT, $data);
        fflush(\STDOUT);
    }

    public function getColumns(): int
    {
        if (null === $this->cachedColumns) {
            $this->refreshDimensions();
        }

        return $this->cachedColumns ?: 80;
    }

    public function getRows(): int
    {
        if (null === $this->cachedRows) {
            $this->refreshDimensions();
        }

        return $this->cachedRows ?: 24;
    }

    public function isKittyProtocolActive(): bool
    {
        return $this->kittyProtocolActive;
    }

    public function bell(): void
    {
        if ('Darwin' === \PHP_OS_FAMILY && file_exists('/System/Library/Sounds/Glass.aiff')) {
            // On macOS, play the system sound in the background to avoid
            // blocking the event loop.
            $this->fireAndForget(['afplay', '/System/Library/Sounds/Glass.aiff']);

            return;
        }

        $this->write("\x07");
    }

    public function isVirtual(): bool
    {
        return false;
    }

    /**
     * Start a command in the background (fire-and-forget).
     *
     * The command is backgrounded via the shell so that proc_close()
     * returns immediately without waiting for it to finish, and without
     * leaking process resources or accumulating zombies.
     *
     * @param list<string> $command
     */
    private function fireAndForget(array $command): void
    {
        $cmd = implode(' ', array_map('escapeshellarg', $command)).' >/dev/null 2>&1 &';
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (\is_resource($process)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    /**
     * Refresh terminal dimensions.
     *
     * This runs on every SIGWINCH, so the native call matters: it replaces a fork and an exec
     * of stty with an ioctl on each resize event.
     */
    private function refreshDimensions(): void
    {
        $native = $this->nativeTerminal ?? self::createNativeTerminal();

        if (null !== $native && false !== $size = $native->getSize()) {
            $this->cachedColumns = $size->cols;
            $this->cachedRows = $size->rows;

            return;
        }

        // Query terminal size directly using stty
        // shell_exec is required here because stty must operate on the
        // process's own tty; proc_open gives the child a pipe, not the tty.
        $sttyOutput = shell_exec('stty size 2>/dev/null');

        if (null !== $sttyOutput && false !== $sttyOutput && preg_match('/^(\d+)\s+(\d+)$/', trim($sttyOutput), $matches)) {
            $this->cachedRows = (int) $matches[1];
            $this->cachedColumns = (int) $matches[2];
        } else {
            // Default fallback
            $this->cachedColumns = 80;
            $this->cachedRows = 24;
        }
    }

    /**
     * Set up StdinBuffer to split batched input into individual sequences.
     */
    private function setupStdinBuffer(): void
    {
        $this->stdinBuffer = new StdinBuffer($this->eventDispatcher);

        // Kitty protocol response pattern: \x1b[?<flags>u
        $kittyResponsePattern = '/^\x1b\[\?(\d+)u$/';

        // Forward individual sequences to the input handler
        $this->stdinBuffer->onData(function (string $sequence) use ($kittyResponsePattern): void {
            // Check for Kitty protocol response (only if not already enabled)
            if (!$this->kittyProtocolActive && preg_match($kittyResponsePattern, $sequence)) {
                $this->kittyProtocolActive = true;
                // Enable Kitty keyboard protocol with enhanced features
                // Flag 1 = disambiguate escape codes
                // Flag 2 = report event types (press/repeat/release)
                // Flag 4 = report alternate keys
                $this->write("\x1b[>7u");

                // Notify the TUI that Kitty protocol is active
                if (null !== $this->onKittyProtocolActivated) {
                    ($this->onKittyProtocolActivated)();
                }

                return; // Don't forward protocol response to TUI
            }

            if (null !== $this->onInput) {
                ($this->onInput)($sequence);
            }
        });

        // Re-wrap paste content with bracketed paste markers
        $this->stdinBuffer->onPaste(function (string $content): void {
            if (null !== $this->onInput) {
                ($this->onInput)("\x1b[200~".$content."\x1b[201~");
            }
        });
    }

    private function drainNativeInput(): void
    {
        while (null !== $this->nativeTerminal && null !== $this->stdinBuffer && false !== $event = $this->nativeTerminal->readEvent(0.0)) {
            $this->processNativeEvent($event);

            // An input callback can stop the TUI and clear these properties.
            if (!$this->started) {
                return;
            }
        }
    }

    private function processNativeEvent(array $event): void
    {
        if (null === $this->stdinBuffer) {
            return;
        }

        if ('data' === ($event['type'] ?? null)) {
            if (null !== $this->escapeFlushCallbackId) {
                EventLoop::cancel($this->escapeFlushCallbackId);
                $this->escapeFlushCallbackId = null;
            }

            $data = $event['data'] ?? '';
            if ('' === $data) {
                return;
            }

            // readEvent() deliberately does not align chunks to UTF-8 or escape
            // sequences. Tell StdinBuffer not to reinterpret a one-byte high-bit
            // chunk as Meta.
            $this->stdinBuffer->process($data, true);

            // A real Escape key is indistinguishable from the first byte of an
            // escape sequence until a short inter-byte timeout expires.
            if ("\x1b" === $this->stdinBuffer?->getBuffer()) {
                $this->escapeFlushCallbackId = EventLoop::delay(0.03, function (): void {
                    $this->escapeFlushCallbackId = null;
                    $this->stdinBuffer?->flush();
                });
            }

            return;
        }

        if ('resize' === ($event['type'] ?? null)) {
            $this->cachedColumns = null;
            $this->cachedRows = null;

            if (null !== $this->onResize) {
                ($this->onResize)();
            }

            return;
        }

        if ('key' !== ($event['type'] ?? null)) {
            return;
        }

        foreach ($this->nativeKeySequences($event) as $sequence) {
            $this->stdinBuffer?->process($sequence);
            // Native records already delimit one key, including Escape.
            $this->stdinBuffer?->flush();

            if (!$this->started) {
                return;
            }
        }
    }

    /**
     * Convert a Windows KEY_EVENT_RECORD to sequences already understood by KeyParser.
     *
     * @return list<string>
     */
    private function nativeKeySequences(array $event): array
    {
        $keyDown = (bool) ($event['keyDown'] ?? false);
        $repeatCount = max(1, (int) ($event['repeatCount'] ?? 1));
        $eventType = $keyDown ? 1 : 3;
        $virtualKey = (int) ($event['virtualKeyCode'] ?? 0);
        $modifiers = 1
            + (!empty($event['shift']) ? 1 : 0)
            + (!empty($event['alt']) ? 2 : 0)
            + (!empty($event['ctrl']) ? 4 : 0);

        if (null !== $special = $this->nativeSpecialKeySequence($virtualKey, $modifiers, $eventType)) {
            if (!$keyDown) {
                return [$special];
            }

            $sequences = [$special];
            $repeat = $this->nativeSpecialKeySequence($virtualKey, $modifiers, 2) ?? $special;
            for ($i = 1; $i < $repeatCount; ++$i) {
                $sequences[] = $repeat;
            }

            return $sequences;
        }

        $text = $event['text'] ?? null;
        if (\is_string($text) && '' !== $text) {
            if (!$keyDown) {
                if (null !== $codepoint = $this->nativeModifiedAsciiCodepoint($text, $event)) {
                    return ["\x1b[".$codepoint.';'.$modifiers.':3u'];
                }

                return [];
            }

            if (null !== $codepoint = $this->nativeModifiedAsciiCodepoint($text, $event)) {
                $sequences = ["\x1b[".$codepoint.';'.$modifiers.':1u'];
                for ($i = 1; $i < $repeatCount; ++$i) {
                    $sequences[] = "\x1b[".$codepoint.';'.$modifiers.':2u';
                }

                return $sequences;
            }

            return array_fill(0, $repeatCount, $text);
        }

        $unit = (int) ($event['unicodeCodeUnit'] ?? 0);
        if (!$keyDown) {
            return [];
        }

        if ($unit >= 0xD800 && $unit <= 0xDBFF) {
            $this->pendingHighSurrogate = $unit;

            return [];
        }

        if ($unit >= 0xDC00 && $unit <= 0xDFFF && null !== $this->pendingHighSurrogate) {
            $codepoint = 0x10000 + (($this->pendingHighSurrogate - 0xD800) << 10) + ($unit - 0xDC00);
            $this->pendingHighSurrogate = null;

            return [$this->utf8FromCodepoint($codepoint)];
        }

        $this->pendingHighSurrogate = null;

        return [];
    }

    private function nativeModifiedAsciiCodepoint(string $text, array $event): ?int
    {
        if (empty($event['ctrl']) && empty($event['alt'])) {
            return null;
        }

        if (1 !== \strlen($text)) {
            return null;
        }

        $code = \ord($text);
        if (!empty($event['ctrl']) && $code >= 1 && $code <= 26) {
            return 96 + $code;
        }

        if ($code >= 65 && $code <= 90) {
            return $code + 32;
        }

        return $code >= 32 && $code <= 126 ? $code : null;
    }

    private function nativeSpecialKeySequence(int $virtualKey, int $modifiers, int $eventType): ?string
    {
        if (isset([0x25 => 'D', 0x26 => 'A', 0x27 => 'C', 0x28 => 'B'][$virtualKey])) {
            $final = [0x25 => 'D', 0x26 => 'A', 0x27 => 'C', 0x28 => 'B'][$virtualKey];

            return "\x1b[1;".$modifiers.':'.$eventType.$final;
        }

        if (isset([0x23 => 'F', 0x24 => 'H'][$virtualKey])) {
            $final = [0x23 => 'F', 0x24 => 'H'][$virtualKey];

            return "\x1b[1;".$modifiers.':'.$eventType.$final;
        }

        if (isset([0x21 => 5, 0x22 => 6, 0x2D => 2, 0x2E => 3][$virtualKey])) {
            $code = [0x21 => 5, 0x22 => 6, 0x2D => 2, 0x2E => 3][$virtualKey];

            return "\x1b[".$code.';'.$modifiers.':'.$eventType.'~';
        }

        if (isset([0x08 => 127, 0x09 => 9, 0x0D => 13, 0x1B => 27][$virtualKey])) {
            $codepoint = [0x08 => 127, 0x09 => 9, 0x0D => 13, 0x1B => 27][$virtualKey];

            return "\x1b[".$codepoint.';'.$modifiers.':'.$eventType.'u';
        }

        if ($virtualKey >= 0x70 && $virtualKey <= 0x7B) {
            if (3 === $eventType) {
                return null;
            }

            $function = $virtualKey - 0x70 + 1;
            if ($function <= 4) {
                $final = ['P', 'Q', 'R', 'S'][$function - 1];

                return 1 === $modifiers ? "\x1bO".$final : "\x1b[1;".$modifiers.$final;
            }

            $code = [5 => 15, 6 => 17, 7 => 18, 8 => 19, 9 => 20, 10 => 21, 11 => 23, 12 => 24][$function];

            return 1 === $modifiers ? "\x1b[".$code.'~' : "\x1b[".$code.';'.$modifiers.'~';
        }

        return null;
    }

    private function utf8FromCodepoint(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return \chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return \chr(0xC0 | ($codepoint >> 6))
                .\chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return \chr(0xE0 | ($codepoint >> 12))
                .\chr(0x80 | (($codepoint >> 6) & 0x3F))
                .\chr(0x80 | ($codepoint & 0x3F));
        }

        return \chr(0xF0 | ($codepoint >> 18))
            .\chr(0x80 | (($codepoint >> 12) & 0x3F))
            .\chr(0x80 | (($codepoint >> 6) & 0x3F))
            .\chr(0x80 | ($codepoint & 0x3F));
    }

    private static function supportsNativeInput(): bool
    {
        if (!\extension_loaded('terminal') || !class_exists(\Io\Terminal\Terminal::class, false)) {
            return false;
        }

        $version = phpversion('terminal');

        return false !== $version
            && version_compare($version, '1.1.0', '>=')
            && version_compare($version, '2.0.0', '<');
    }

    /**
     * Returns a native terminal when ext-terminal is loaded in a version this component knows how to call.
     *
     * The extension promises its public signatures only within 1.x, so a future major is treated
     * like a missing extension instead of being called blindly.
     */
    private static function createNativeTerminal(): ?\Io\Terminal\Terminal
    {
        if (\extension_loaded('terminal') && version_compare(phpversion('terminal'), '1.0.0', '>=') && version_compare(phpversion('terminal'), '2.0.0', '<') && class_exists(\Io\Terminal\Terminal::class, false)) {
            return \Io\Terminal\Terminal::create();
        }

        return null;
    }

    /**
     * Check if stty is available on this system.
     */
    private function hasSttyAvailable(): bool
    {
        static $available = null;

        if (null !== $available) {
            return $available;
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            return $available = false;
        }

        return $available = (bool) shell_exec('stty 2>/dev/null');
    }
}
