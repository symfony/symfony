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
     * multiple input chunks. Modifies $data in place to remove
     * paste markers and consumed content.
     *
     * @param string $data Input data; modified to contain only the portion
     *                     after the paste end marker (if any), or emptied
     *                     if still buffering
     *
     * @return string|null The complete pasted text when the end marker is
     *                     received, or null if still buffering
     */
    private function processBracketedPaste(string &$data): ?string
    {
        if (str_contains($data, "\x1b[200~")) {
            $this->inPaste = true;
            $this->pasteBuffer = '';
            $data = str_replace("\x1b[200~", '', $data);
        }

        if (!$this->inPaste) {
            return null;
        }

        if (false !== $endIndex = strpos($data, "\x1b[201~")) {
            $this->pasteBuffer .= substr($data, 0, $endIndex);
            $pastedText = $this->pasteBuffer;
            $this->inPaste = false;
            $this->pasteBuffer = '';
            $data = substr($data, $endIndex + 6);

            return $pastedText;
        }

        $this->pasteBuffer .= $data;
        $data = '';

        return null;
    }
}
