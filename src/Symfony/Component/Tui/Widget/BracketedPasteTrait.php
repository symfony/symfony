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
    private string $pasteBuffer = '';

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
     *                     caller can surface a visible notice.
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
        }

        if (false !== $endIndex = strpos($data, "\x1b[201~")) {
            $this->pasteBuffer .= substr($data, 0, $endIndex);
            $pastedText = $this->pasteBuffer;
            $this->inPaste = false;
            $this->pasteBuffer = '';
            $data = $prefix.substr($data, $endIndex + 6);

            return $pastedText;
        }

        $this->pasteBuffer .= $data;
        $data = $prefix;

        // Cap reached without an end marker: discard the partial paste and
        // exit paste mode. Returns a visible overflow notice in place of
        // the partial content so the caller can show the user why their
        // paste did not land. Defense against unbounded buffering from a
        // missing/spoofed end marker.
        if (\strlen($this->pasteBuffer) > self::MAX_PASTE_BYTES) {
            $this->pasteBuffer = '';
            $this->inPaste = false;

            return self::PASTE_OVERFLOW_MESSAGE;
        }

        return null;
    }
}
