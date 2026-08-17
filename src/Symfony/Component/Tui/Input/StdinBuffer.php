<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Input;

use Symfony\Component\Tui\Event\PasteCompletedEvent;
use Symfony\Component\Tui\Event\PasteStartedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Buffers and splits batched stdin input into individual sequences.
 *
 * This ensures components receive single key events, making key parsing work correctly.
 * Also handles bracketed paste mode.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class StdinBuffer
{
    private const int MAX_PASTE_BYTES = 16 * 1024 * 1024;
    private const int MAX_PENDING_BYTES = 1024 * 1024;
    private const string PASTE_OVERFLOW_MESSAGE = '[paste exceeded 16 MiB limit]';

    private string $buffer = '';

    /** @var (\Closure(string): void)|null */
    private ?\Closure $onData = null;

    /** @var (\Closure(string): void)|null */
    private ?\Closure $onPaste = null;

    private bool $inPaste = false;
    private bool $pasteOverflowed = false;
    private string $pasteBuffer = '';

    public function __construct(
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * Set callback for individual key sequences.
     *
     * @param callable(string): void $callback
     */
    public function onData(callable $callback): void
    {
        $this->onData = $callback(...);
    }

    /**
     * Set callback for paste content.
     *
     * @param callable(string): void $callback
     */
    public function onPaste(callable $callback): void
    {
        $this->onPaste = $callback(...);
    }

    /**
     * Process incoming data and emit individual sequences.
     */
    public function process(string $data): void
    {
        // Handle high-byte meta encoding: some terminals (e.g. macOS Terminal.app
        // with "Use Option as Meta key") send Alt+key as a single byte with the
        // high bit set (byte | 0x80) instead of the standard ESC + key sequence.
        // Convert single high bytes to ESC + (byte & 0x7F) to normalize input.
        // This matches the Pi reference implementation.
        if (1 === \strlen($data) && \ord($data) > 127) {
            $data = "\x1b".\chr(\ord($data) - 128);
        }

        $this->buffer .= $data;

        while ('' !== $this->buffer) {
            // Check for bracketed paste start. While a paste is running the
            // marker is content, not a new paste: the end marker is the only
            // thing that closes it.
            if (!$this->inPaste && str_starts_with($this->buffer, "\x1b[200~")) {
                $this->inPaste = true;
                $this->pasteBuffer = '';
                $this->buffer = substr($this->buffer, 6);
                $this->eventDispatcher?->dispatch(new PasteStartedEvent());
                continue;
            }

            // If in paste mode, accumulate until end marker
            if ($this->inPaste) {
                if (false !== $endPos = strpos($this->buffer, "\x1b[201~")) {
                    $content = null;

                    if (!$this->pasteOverflowed) {
                        $this->pasteBuffer .= substr($this->buffer, 0, $endPos);
                        $content = $this->pasteBuffer;
                    }

                    $this->buffer = substr($this->buffer, $endPos + 6);
                    $this->inPaste = false;
                    $this->pasteOverflowed = false;
                    $this->pasteBuffer = '';

                    $this->completePaste($content);
                } else {
                    // Still waiting for the end marker. A read can split it, so
                    // hold back a trailing part of it instead of moving it into
                    // the content, where it can no longer close the paste.
                    $hold = 0;
                    for ($n = min(5, \strlen($this->buffer)); $n > 0; --$n) {
                        if (str_starts_with("\x1b[201~", substr($this->buffer, -$n))) {
                            $hold = $n;
                            break;
                        }
                    }

                    if (0 < $hold) {
                        $chunk = substr($this->buffer, 0, -$hold);
                        $this->buffer = substr($this->buffer, -$hold);
                    } else {
                        $chunk = $this->buffer;
                        $this->buffer = '';
                    }

                    if (!$this->pasteOverflowed) {
                        $this->pasteBuffer .= $chunk;

                        // Cap reached without an end marker: discard the
                        // content and stop accumulating, as a defense against
                        // unbounded buffering from a missing/spoofed end
                        // marker. The paste stays open, because the terminal
                        // is still sending it and the rest of it must not be
                        // delivered as key events. A visible overflow notice
                        // goes out through the paste callback so the user can
                        // see why their paste did not land. The check has to
                        // run on the held path too: a writer that ends every
                        // chunk with a byte of the end marker would otherwise
                        // never reach it.
                        if (\strlen($this->pasteBuffer) > self::MAX_PASTE_BYTES) {
                            $this->pasteBuffer = '';
                            $this->pasteOverflowed = true;

                            if (null !== $this->onPaste) {
                                ($this->onPaste)(self::PASTE_OVERFLOW_MESSAGE);
                            }
                        }
                    }

                    if (0 < $hold) {
                        break;
                    }
                }
                continue;
            }

            // Try to extract a complete sequence
            $sequence = $this->extractSequence();

            if (null === $sequence) {
                // Buffer might contain incomplete sequence, wait for more data.
                // If we cross the cap without producing a complete sequence
                // (e.g. unterminated OSC/DCS), discard the pending buffer
                // silently to bound memory.
                if (\strlen($this->buffer) > self::MAX_PENDING_BYTES) {
                    $this->buffer = '';
                }
                break;
            }

            if (null !== $this->onData) {
                ($this->onData)($sequence);
            }
        }
    }

    /**
     * Get any remaining buffered data.
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Clear the buffer.
     */
    public function clear(): void
    {
        $wasInPaste = $this->inPaste;
        $this->buffer = '';
        $this->pasteBuffer = '';
        $this->inPaste = false;
        $this->pasteOverflowed = false;

        if ($wasInPaste) {
            $this->completePaste(null);
        }
    }

    /**
     * Flush any pending data in the buffer.
     *
     * This is used when no more input is expected (e.g., end of test input).
     * A standalone ESC that was waiting for more characters will be emitted.
     */
    public function flush(): void
    {
        // If we have a single ESC waiting, emit it as a standalone Escape key.
        // While a paste is open that ESC is the start of a held end marker
        // instead, and emitting it would leave the paste with no way to close.
        if (!$this->inPaste && "\x1b" === $this->buffer && null !== $this->onData) {
            ($this->onData)("\x1b");
            $this->buffer = '';
        }
    }

    private function completePaste(?string $content): void
    {
        if (null !== $content && null !== $this->onPaste) {
            ($this->onPaste)($content);
        }
        $this->eventDispatcher?->dispatch(new PasteCompletedEvent());
    }

    /**
     * Extract a complete sequence from the buffer.
     */
    private function extractSequence(): ?string
    {
        if ('' === $this->buffer) {
            return null;
        }

        $first = $this->buffer[0];

        // Regular printable ASCII character
        if ("\x1b" !== $first) {
            // Check for multi-byte UTF-8
            $ord = \ord($first);
            if ($ord >= 0x80) {
                $len = $this->getUtf8CharLength($ord);
                if (\strlen($this->buffer) >= $len) {
                    $sequence = substr($this->buffer, 0, $len);
                    $this->buffer = substr($this->buffer, $len);

                    return $sequence;
                }

                // Incomplete UTF-8 sequence
                return null;
            }

            $this->buffer = substr($this->buffer, 1);

            return $first;
        }

        // ESC sequence
        if (\strlen($this->buffer) < 2) {
            // Might be incomplete, or just ESC key
            return null;
        }

        $second = $this->buffer[1];

        // ESC ESC (double escape) - need to look ahead to determine behavior
        if ("\x1b" === $second) {
            // Need at least 3 chars to decide
            if (\strlen($this->buffer) < 3) {
                return null; // Wait for more data
            }

            $third = $this->buffer[2];

            // If third char starts a CSI or SS3 sequence, emit first ESC and continue
            // This handles: ESC ESC [ ... or ESC ESC O ...
            if ('[' === $third || 'O' === $third) {
                $this->buffer = substr($this->buffer, 1);

                return "\x1b";
            }

            // Otherwise it's a double-escape followed by something else
            $this->buffer = substr($this->buffer, 2);

            return "\x1b\x1b";
        }

        // Sequence type dispatch based on second byte:
        // CSI (ESC [), SS3 (ESC O), OSC (ESC ]), DCS (ESC P), APC (ESC _)
        return match ($second) {
            '[' => $this->extractCsiSequence(),
            'O' => $this->extractSs3Sequence(),
            ']' => $this->extractOscSequence(),
            'P' => $this->extractDcsSequence(),
            '_' => $this->extractOscSequence(), // APC uses the same terminator rules
            default => $this->extractAltKey($second),
        };
    }

    /**
     * Extract Alt+key or Ctrl+Alt+key: ESC followed by any non-ESC byte that
     * isn't a sequence initiator. This covers Alt+letter,
     * Alt+Backspace (\x1b\x7f), Alt+Space (\x1b\x20), Alt+Enter
     * (\x1b\r), Ctrl+Alt+] (\x1b\x1d), etc.
     */
    private function extractAltKey(string $second): string
    {
        $this->buffer = substr($this->buffer, 2);

        return "\x1b".$second;
    }

    /**
     * Extract CSI sequence (ESC [ ... terminator).
     */
    private function extractCsiSequence(): ?string
    {
        $len = \strlen($this->buffer);

        // Old-style mouse sequence: ESC [ M + 3 bytes
        if ($len >= 3 && 'M' === $this->buffer[2]) {
            if ($len < 6) {
                return null;
            }

            $sequence = substr($this->buffer, 0, 6);
            $this->buffer = substr($this->buffer, 6);

            return $sequence;
        }

        for ($i = 2; $i < $len; ++$i) {
            $char = $this->buffer[$i];

            // CSI terminators: @ through ~
            if ($char >= '@' && $char <= '~') {
                $sequence = substr($this->buffer, 0, $i + 1);
                $this->buffer = substr($this->buffer, $i + 1);

                return $sequence;
            }

            // Invalid character in CSI sequence
            if ($char < ' ' || $char > '?') {
                // Malformed sequence, just return what we have
                $this->buffer = substr($this->buffer, 1);

                return "\x1b";
            }
        }

        // Incomplete sequence
        return null;
    }

    /**
     * Extract SS3 sequence (ESC O letter).
     */
    private function extractSs3Sequence(): ?string
    {
        if (\strlen($this->buffer) < 3) {
            return null;
        }

        $sequence = substr($this->buffer, 0, 3);
        $this->buffer = substr($this->buffer, 3);

        return $sequence;
    }

    /**
     * Extract OSC sequence (ESC ] ... BEL or ESC ] ... ST).
     */
    private function extractOscSequence(): ?string
    {
        $len = \strlen($this->buffer);

        for ($i = 2; $i < $len; ++$i) {
            // BEL terminator
            if ("\x07" === $this->buffer[$i]) {
                $sequence = substr($this->buffer, 0, $i + 1);
                $this->buffer = substr($this->buffer, $i + 1);

                return $sequence;
            }

            // ST terminator (ESC \)
            if ("\x1b" === $this->buffer[$i] && isset($this->buffer[$i + 1]) && '\\' === $this->buffer[$i + 1]) {
                $sequence = substr($this->buffer, 0, $i + 2);
                $this->buffer = substr($this->buffer, $i + 2);

                return $sequence;
            }
        }

        // Incomplete sequence
        return null;
    }

    /**
     * Extract DCS sequence (ESC P ... ST).
     */
    private function extractDcsSequence(): ?string
    {
        $len = \strlen($this->buffer);

        for ($i = 2; $i < $len; ++$i) {
            if ("\x1b" === $this->buffer[$i] && isset($this->buffer[$i + 1]) && '\\' === $this->buffer[$i + 1]) {
                $sequence = substr($this->buffer, 0, $i + 2);
                $this->buffer = substr($this->buffer, $i + 2);

                return $sequence;
            }
        }

        return null;
    }

    /**
     * Get the expected length of a UTF-8 character from its first byte.
     */
    private function getUtf8CharLength(int $ord): int
    {
        return match (true) {
            $ord < 0xC0 => 1, // ASCII or invalid continuation byte
            $ord < 0xE0 => 2,
            $ord < 0xF0 => 3,
            $ord < 0xF8 => 4,
            default => 1, // Invalid, treat as single byte
        };
    }
}
