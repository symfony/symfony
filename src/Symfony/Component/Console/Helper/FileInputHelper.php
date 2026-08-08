<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\InvalidFileException;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\FileQuestion;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Terminal\Image\ImageProtocolInterface;
use Symfony\Component\Console\Terminal\Image\ITerm2Protocol;
use Symfony\Component\Console\Terminal\Image\KittyGraphicsProtocol;

/**
 * Orchestrates file input handling through paste detection or path input.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class FileInputHelper
{
    private const BPM_ENABLE = "\x1b[?2004h";
    private const BPM_DISABLE = "\x1b[?2004l";
    private const PASTE_START = "\x1b[200~";
    private const PASTE_END = "\x1b[201~";
    private const MAX_PASTE_BYTES = 16 * 1024 * 1024;

    private ?ImageProtocolInterface $protocol = null;

    /**
     * @param resource $inputStream
     */
    public function readFileInput($inputStream, OutputInterface $output, FileQuestion $question): InputFile
    {
        if ($canPaste = $question->isPasteAllowed() && Terminal::supportsImageProtocol() && Terminal::hasSttyAvailable()) {
            $this->protocol = $this->detectProtocol();
        }

        $file = null;
        $inputHelper = null;

        try {
            if ($canPaste) {
                $inputHelper = new TerminalInputHelper($inputStream);
                $output->write(self::BPM_ENABLE);
                shell_exec('stty -icanon -echo');

                $file = $this->readWithPasteDetection($inputStream, $output, $question, $inputHelper);
            } elseif ($question->isPathAllowed()) {
                $file = $this->readPathInput($inputStream);
            } else {
                throw new MissingInputException('Terminal does not support image paste and path input is disabled.');
            }
        } finally {
            if ($canPaste) {
                $output->write(self::BPM_DISABLE);
                $inputHelper?->finish();
            }
        }

        if (!$file->isValid()) {
            throw new InvalidFileException(\sprintf('File "%s" is not valid or readable.', $file->getPathname()));
        }

        $this->displayFile($output, $file);

        return $file;
    }

    public function displayFile(OutputInterface $output, InputFile $file): void
    {
        $path = self::sanitizeForDisplay((string) $file->getRealPath());
        $filename = self::sanitizeForDisplay($file->getFilename());
        $link = \sprintf('<href=file://%s>%s</>', OutputFormatter::escape($path), OutputFormatter::escape($filename));

        if ($output->isVeryVerbose()) {
            $output->writeln(\sprintf('<info>%s</info> %s (<comment>%s, %s</comment>)', "\u{1F4CE}", $link, OutputFormatter::escape(self::sanitizeForDisplay($file->getMimeType() ?? 'unknown')), $file->getHumanReadableSize()));
        } else {
            $output->writeln(\sprintf('<info>%s</info> %s', "\u{1F4CE}", $link));
        }

        if (Terminal::supportsImageProtocol() && $this->isDisplayableImage($file)) {
            $this->displayThumbnail($output, $file);
        }
    }

    /**
     * @param resource $inputStream
     */
    private function readWithPasteDetection($inputStream, OutputInterface $output, FileQuestion $question, TerminalInputHelper $inputHelper): InputFile
    {
        $buffer = '';
        $inPaste = false;
        $pasteBuffer = '';
        $scanned = 0;

        while (true) {
            $inputHelper->waitForInput();
            $chunk = fread($inputStream, 8192);

            if (false === $chunk || '' === $chunk) {
                if ('' === $buffer && '' === $pasteBuffer) {
                    throw new MissingInputException('Aborted.');
                }
                break;
            }

            $buffer .= $chunk;

            if (\strlen($buffer) > self::MAX_PASTE_BYTES) {
                throw new InvalidFileException(\sprintf('Pasted input exceeds the maximum allowed size of %d bytes.', self::MAX_PASTE_BYTES));
            }

            // Scan whole reads at once; appending byte by byte is quadratic for large pastes.
            if (!$inPaste) {
                // Look back so a marker split across two reads is still matched.
                $pasteStart = strpos($buffer, self::PASTE_START, max(0, $scanned - \strlen(self::PASTE_START) + 1));
                $newline = $scanned + strcspn($buffer, "\r\n", $scanned);
                $hasNewline = $newline < \strlen($buffer);

                if (false === $pasteStart || ($hasNewline && $newline <= $pasteStart + \strlen(self::PASTE_START) - 1)) {
                    if ($hasNewline) {
                        $buffer = substr($buffer, 0, $newline);
                        break;
                    }

                    $scanned = \strlen($buffer);
                    continue;
                }

                $inPaste = true;
                $buffer = substr($buffer, 0, $pasteStart).substr($buffer, $pasteStart + \strlen(self::PASTE_START));
                $scanned = $pasteStart;
            }

            $pasteEnd = strpos($buffer, self::PASTE_END, max(0, $scanned - \strlen(self::PASTE_END) + 1));

            if (false !== $pasteEnd) {
                $pasteBuffer = substr($buffer, 0, $pasteEnd);
                $buffer = substr($buffer, 0, $pasteEnd + \strlen(self::PASTE_END));
                break;
            }

            $scanned = \strlen($buffer);
        }

        if ('' !== $pasteBuffer) {
            if (null !== $this->protocol && $this->protocol->detectPastedImage($pasteBuffer)) {
                $decoded = $this->protocol->decode($pasteBuffer);

                if ('' === $decoded['data']) {
                    throw new InvalidFileException('The pasted image could not be decoded.');
                }

                return InputFile::fromData($decoded['data'], $decoded['format']);
            }

            $path = trim($pasteBuffer);
            if ('' !== $path && $question->isPathAllowed()) {
                return InputFile::fromPath($path);
            }
        }

        $path = trim($buffer);
        if ('' !== $path && $question->isPathAllowed()) {
            return InputFile::fromPath($path);
        }

        throw new MissingInputException('No file input provided.');
    }

    /**
     * @param resource $inputStream
     */
    private function readPathInput($inputStream): InputFile
    {
        if (!$isBlocked = stream_get_meta_data($inputStream)['blocked'] ?? true) {
            stream_set_blocking($inputStream, true);
        }

        $path = fgets($inputStream);

        if (!$isBlocked) {
            stream_set_blocking($inputStream, false);
        }

        if (false === $path) {
            throw new MissingInputException('Aborted.');
        }

        if ('' === $path = trim($path)) {
            throw new MissingInputException('No file path provided.');
        }

        return InputFile::fromPath($path);
    }

    private function detectProtocol(): ?ImageProtocolInterface
    {
        if (Terminal::supportsKittyGraphics()) {
            return new KittyGraphicsProtocol();
        }

        if (Terminal::supportsITerm2Images()) {
            return new ITerm2Protocol();
        }

        return null;
    }

    /**
     * Strips terminal-escape introducer bytes from a file name or path before it is
     * echoed back to the user, so a crafted name cannot inject escape sequences.
     * OutputFormatter::escape() only neutralizes "<" and ">", not control bytes.
     *
     * Removes C0 controls (except TAB and LF), DEL, and the UTF-8 encoding of C1
     * controls, matching Tui's StringUtils::stripControlBytes().
     *
     * The replacement is repeated until it reaches a fixed point: removing a byte
     * can splice two survivors into a fresh control sequence (e.g. "\xc2\x1b\x9b"
     * leaves "\xc2\x9b", the UTF-8 encoding of U+009B), so a single pass is not enough.
     */
    private static function sanitizeForDisplay(string $value): string
    {
        do {
            $value = preg_replace("/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $value, -1, $count) ?? '';
        } while ($count > 0);

        return $value;
    }

    private function isDisplayableImage(InputFile $file): bool
    {
        if (null === $mimeType = $file->getMimeType()) {
            return false;
        }

        return str_starts_with($mimeType, 'image/');
    }

    private function displayThumbnail(OutputInterface $output, InputFile $file): void
    {
        try {
            $contents = $file->getContents();
        } catch (InvalidFileException) {
            return;
        }

        $protocol = Terminal::supportsKittyGraphics() ? new KittyGraphicsProtocol() : new ITerm2Protocol();

        $output->write($protocol->encode($contents, 16));
        $output->writeln('');
    }
}
