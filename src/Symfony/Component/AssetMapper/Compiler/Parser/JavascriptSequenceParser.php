<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler\Parser;

/**
 * Parses JavaScript content to identify sequences of strings, comments, etc.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
final class JavascriptSequenceParser
{
    private const STATE_DEFAULT = 0;
    private const STATE_COMMENT = 1;
    private const STATE_STRING = 2;

    private int $cursor = 0;

    private int $contentEnd;

    private string $pattern;

    private int $currentSequenceType = self::STATE_DEFAULT;

    private ?int $currentSequenceEnd = null;

    private const COMMENT_SEPARATORS = [
        '/*',   // Multi-line comment
        '//',   // Single-line comment
        '"',    // Double quote
        '\'',   // Single quote
        '`',    // Backtick
    ];

    public function __construct(
        private readonly string $content,
    ) {
        $this->contentEnd = \strlen($content);

        $this->pattern ??= '/'.implode('|', array_map(
            fn (string $ch): string => preg_quote($ch, '/'),
            self::COMMENT_SEPARATORS
        )).'/';
    }

    public function isString(): bool
    {
        return self::STATE_STRING === $this->currentSequenceType;
    }

    public function isExecutable(): bool
    {
        return self::STATE_DEFAULT === $this->currentSequenceType;
    }

    public function isComment(): bool
    {
        return self::STATE_COMMENT === $this->currentSequenceType;
    }

    public function parseUntil(int $position): void
    {
        if ($position > $this->contentEnd) {
            throw new \RuntimeException('Cannot parse beyond the end of the content.');
        }
        if ($position < $this->cursor) {
            throw new \RuntimeException('Cannot parse backwards.');
        }

        while ($this->cursor <= $position) {
            // Current CodeSequence ?
            if (null !== $this->currentSequenceEnd) {
                if ($this->currentSequenceEnd > $position) {
                    $this->cursor = $position;

                    return;
                }

                $this->cursor = $this->currentSequenceEnd;
                $this->setSequence(self::STATE_DEFAULT, null);
            }

            preg_match($this->pattern, $this->content, $matches, \PREG_OFFSET_CAPTURE, $this->cursor);
            if (!$matches) {
                $this->endsWithSequence(self::STATE_DEFAULT, $position);

                return;
            }

            $matchPos = (int) $matches[0][1];
            $matchChar = $matches[0][0];

            if ($matchPos > $position) {
                $this->setSequence(self::STATE_DEFAULT, $matchPos - 1);
                $this->cursor = $position;

                return;
            }

            // Multi-line comment
            if ('/*' === $matchChar) {
                if (false === $endPos = strpos($this->content, '*/', $matchPos + 2)) {
                    $this->endsWithSequence(self::STATE_COMMENT, $position);

                    return;
                }

                $this->cursor = min($endPos + 2, $position);
                $this->setSequence(self::STATE_COMMENT, $endPos + 2);
                continue;
            }

            // Single-line comment
            if ('//' === $matchChar) {
                if (false === $endPos = strpos($this->content, "\n", $matchPos + 2)) {
                    $this->endsWithSequence(self::STATE_COMMENT, $position);

                    return;
                }

                $this->cursor = min($endPos + 1, $position);
                $this->setSequence(self::STATE_COMMENT, $endPos + 1);
                continue;
            }

            if ('"' === $matchChar || "'" === $matchChar || '`' === $matchChar) {
                $endPos = $matchPos + 1;
                while (false !== $endPos = strpos($this->content, $matchChar, $endPos)) {
                    $backslashes = 0;
                    $i = $endPos - 1;
                    while ($i >= 0 && '\\' === $this->content[$i]) {
                        ++$backslashes;
                        --$i;
                    }

                    if (0 === $backslashes % 2) {
                        break;
                    }

                    ++$endPos;
                }

                if (false === $endPos) {
                    $this->endsWithSequence(self::STATE_STRING, $position);

                    return;
                }

                $this->cursor = min($endPos + 1, $position);
                $this->setSequence(self::STATE_STRING, $endPos + 1);
                continue;
            }

            // Fallback
            $this->cursor = $matchPos + 1;
        }
    }

    /**
     * @param int<self::STATE_*> $type
     */
    private function endsWithSequence(int $type, int $cursor): void
    {
        $this->cursor = $cursor;
        $this->currentSequenceType = $type;
        $this->currentSequenceEnd = $this->contentEnd;
    }

    /**
     * @param int<self::STATE_*> $type
     */
    private function setSequence(int $type, ?int $end = null): void
    {
        $this->currentSequenceType = $type;
        $this->currentSequenceEnd = $end;
    }
}
