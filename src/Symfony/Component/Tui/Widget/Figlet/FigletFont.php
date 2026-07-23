<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Figlet;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Parses and represents a FIGlet font (.flf file).
 *
 * The FIGlet font format stores ASCII art representations of characters.
 * Each character is defined as a fixed number of lines (the font height).
 * Lines are terminated by the font's "end mark" character (@), and the
 * last line of each character uses a double end mark (@@).
 *
 * The "hardblank" character (typically $) is rendered as a visible space
 * that prevents smushing; it's replaced with a regular space on output.
 *
 * @see https://github.com/cmatsuoka/figlet/blob/master/figfont.txt
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FigletFont
{
    private const int MAX_DECOMPRESSED_BYTES = 4 * 1024 * 1024;

    /** @var array<int, string[]> codepoint → array of lines */
    private array $characters = [];

    /**
     * Load a font from a .flf file path.
     */
    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(\sprintf('FIGlet font file "%s" does not exist.', $path));
        }

        if (false === $content = file_get_contents($path)) {
            throw new InvalidArgumentException(\sprintf('Cannot read FIGlet font file "%s".', $path));
        }

        // Auto-detect ZIP-compressed .flf files (some font sites distribute them this way)
        if (str_starts_with($content, "PK\x03\x04")) {
            $content = self::extractFromZip($path);
        }

        return self::parse($content);
    }

    /**
     * Parse a FIGlet font from its raw string content.
     */
    public static function parse(string $content): self
    {
        $lines = explode("\n", $content);

        // Parse header: flf2a<hardblank> <height> <baseline> <maxLength> <oldLayout> <commentLines> ...
        $header = $lines[0];
        if (!str_starts_with($header, 'flf2')) {
            throw new InvalidArgumentException('Invalid FIGlet font: missing flf2 signature.');
        }

        $hardblank = $header[5]; // Character after "flf2a"
        $headerParts = preg_split('/\s+/', $header) ?: [];
        $height = (int) ($headerParts[1] ?? 0);
        $commentLines = (int) ($headerParts[5] ?? 0);

        $font = new self($height, $hardblank);

        // Character data starts after header + comments
        $lineIndex = 1 + $commentLines;

        // Phase 1: Parse required ASCII characters (32–126 = 95 characters)
        for ($codepoint = 32; $codepoint <= 126; ++$codepoint) {
            $charLines = $font->readCharacterLines($lines, $lineIndex);
            if (null === $charLines) {
                break;
            }
            $font->characters[$codepoint] = $charLines;
        }

        // Phase 2: Parse code-tagged characters (extended)
        while ($lineIndex < \count($lines)) {
            $tagLine = $lines[$lineIndex] ?? '';

            // Code-tagged lines start with a number
            if (!preg_match('/^(-?\d+)/', $tagLine, $matches)) {
                break;
            }

            $codepoint = (int) $matches[1];
            ++$lineIndex;

            $charLines = $font->readCharacterLines($lines, $lineIndex);
            if (null === $charLines) {
                break;
            }
            $font->characters[$codepoint] = $charLines;
        }

        return $font;
    }

    /**
     * Get the font height in lines.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Get the character art lines for a given codepoint.
     *
     * @return string[] Array of $height lines, or empty strings if character is not defined
     */
    public function getCharacter(int $codepoint): array
    {
        return $this->characters[$codepoint] ?? array_fill(0, $this->height, '');
    }

    /**
     * Check if a character is defined in this font.
     */
    public function hasCharacter(int $codepoint): bool
    {
        return isset($this->characters[$codepoint]);
    }

    private function __construct(
        private int $height,
        private string $hardblank,
    ) {
    }

    /**
     * Read one character's worth of lines from the font data.
     *
     * @param string[] $lines     All lines of the font file
     * @param int      $lineIndex Current read position (modified by reference)
     *
     * @return string[]|null The character lines, or null if not enough data
     */
    private function readCharacterLines(array $lines, int &$lineIndex): ?array
    {
        $charLines = [];

        for ($row = 0; $row < $this->height; ++$row) {
            if ($lineIndex >= \count($lines)) {
                return null;
            }

            $line = $lines[$lineIndex];
            ++$lineIndex;

            // Strip end marks (@ or @@) from the right
            $line = rtrim($line, "\r\n");
            $line = preg_replace('/@{1,2}$/', '', $line);

            // Replace hardblank with space
            $charLines[] = str_replace($this->hardblank, ' ', $line);
        }

        return $charLines;
    }

    /**
     * Extract the first .flf file from a ZIP archive.
     */
    private static function extractFromZip(string $path): string
    {
        if (!\extension_loaded('zip')) {
            throw new InvalidArgumentException('The ZIP extension is required to load FIGlet fonts from ZIP archives.');
        }

        $zip = new \ZipArchive();

        if (true !== $zip->open($path)) {
            throw new InvalidArgumentException(\sprintf('Cannot open ZIP archive "%s".', $path));
        }

        try {
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (false !== $name && str_ends_with($name, '.flf')) {
                    $stat = $zip->statIndex($i);
                    if (false !== $stat && isset($stat['size']) && $stat['size'] > self::MAX_DECOMPRESSED_BYTES) {
                        throw new InvalidArgumentException(\sprintf('FIGlet font entry "%s" inside ZIP archive "%s" is too large.', $name, $path));
                    }

                    $content = $zip->getFromIndex($i, self::MAX_DECOMPRESSED_BYTES + 1);
                    if (false !== $content) {
                        if (\strlen($content) > self::MAX_DECOMPRESSED_BYTES) {
                            throw new InvalidArgumentException(\sprintf('FIGlet font entry "%s" inside ZIP archive "%s" is too large.', $name, $path));
                        }

                        return $content;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        throw new InvalidArgumentException(\sprintf('No .flf file found inside ZIP archive "%s".', $path));
    }
}
