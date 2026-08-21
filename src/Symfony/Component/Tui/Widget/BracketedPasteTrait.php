<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

/**
 * Handles bracketed paste mode detection and buffering.
 *
 * Terminals that support bracketed paste wrap pasted text between
 * ESC[200~ (start) and ESC[201~ (end) sequences. This trait
 * accumulates chunks until the end marker is received, then
 * returns the complete paste content.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait BracketedPasteTrait
{
    private const int MAX_PASTE_BYTES = 16 * 1024 * 1024;
    private const string PASTE_OVERFLOW_MESSAGE = '[paste exceeded 16 MiB limit]';

    private bool $inPaste = false;
    private bool $pasteOverflowed = false;
    private string $pasteBuffer = '';
    private string $pasteEndMarkerBuffer = '';

    private function isBufferingPaste(): bool
    {
        return $this->inPaste;
    }

    /**
     * Process bracketed paste sequences in input data.
     *
     * Detects paste start/end markers and buffers content across
     * multiple input chunks. Modifies $data in place to leave the
     * caller with the non-paste portion of the chunk: any bytes that
     * preceded the start marker, plus any bytes after the end marker.
     * Returns the complete pasted text when the end marker is
     * received, or null when still buffering.
     *
     * @param string $data Input data; on return contains the non-paste
     *                     bytes (prefix and/or suffix), or '' while
     *                     buffering or when the chunk was paste-only
     *
     * @return string|null The complete pasted text when the end marker is
     *                     received, or null if still buffering or if no
     *                     paste is in progress. If a paste exceeds the
     *                     internal cap, {@see PASTE_OVERFLOW_MESSAGE} is
     *                     returned in lieu of the partial content so the
     *                     caller can surface a visible notice; the rest of
     *                     that paste is then dropped up to the end marker.
     */
    private function processBracketedPaste(string &$data): ?string
    {
        $prefix = '';

        if (!$this->inPaste) {
            if (false === $start = strpos($data, "\x1b[200~")) {
                return null;
            }

            $prefix = substr($data, 0, $start);
            $data = substr($data, $start + 6);
            $this->inPaste = true;
            $this->pasteBuffer = '';
            $this->pasteEndMarkerBuffer = '';
        }

        if ('' !== $this->pasteEndMarkerBuffer) {
            $data = $this->pasteEndMarkerBuffer.$data;
            $this->pasteEndMarkerBuffer = '';
        }

        if (false !== $endIndex = strpos($data, "\x1b[201~")) {
            $pastedText = null;

            if (!$this->pasteOverflowed) {
                $this->pasteBuffer .= substr($data, 0, $endIndex);
                $pastedText = $this->pasteBuffer;
            }

            $this->inPaste = false;
            $this->pasteOverflowed = false;
            $this->pasteBuffer = '';
            $this->pasteEndMarkerBuffer = '';
            $data = $prefix.substr($data, $endIndex + 6);

            return $pastedText;
        }

        $hold = 0;
        for ($n = min(5, \strlen($data)); $n > 0; --$n) {
            if (str_starts_with("\x1b[201~", substr($data, -$n))) {
                $hold = $n;
                break;
            }
        }

        if (0 < $hold) {
            $chunk = substr($data, 0, -$hold);
            $this->pasteEndMarkerBuffer = substr($data, -$hold);
        } else {
            $chunk = $data;
        }

        $data = $prefix;

        if ($this->pasteOverflowed) {
            return null;
        }

        $this->pasteBuffer .= $chunk;

        // Cap reached without an end marker: discard the content and stop
        // accumulating, as a defense against unbounded buffering from a
        // missing/spoofed end marker. Returns a visible overflow notice in
        // place of the partial content so the caller can show the user why
        // their paste did not land. The paste stays open, because the terminal
        // is still sending it and the rest of it must not be handled as key
        // input.
        if (\strlen($this->pasteBuffer) > self::MAX_PASTE_BYTES) {
            $this->pasteBuffer = '';
            $this->pasteOverflowed = true;

            return self::PASTE_OVERFLOW_MESSAGE;
        }

        return null;
    }
}
