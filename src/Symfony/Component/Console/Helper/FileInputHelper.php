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

use Symfony\Component\Console\Cursor;
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
     * @param resource        $inputStream
     * @param callable():void $writePrompt Renders the prompt of a single-file question
     *
     * @return list<InputFile> A single answer may yield several files, e.g. when dragging and
     *                         dropping multiple files at once on a multiple question
     */
    public function readFileInput($inputStream, OutputInterface $output, FileQuestion $question, callable $writePrompt): array
    {
        if ($canPaste = $question->isPasteAllowed() && Terminal::supportsImageProtocol() && Terminal::hasSttyAvailable()) {
            $this->protocol = $this->detectProtocol();
        } elseif (!$question->isPathAllowed()) {
            throw new MissingInputException('Terminal does not support image paste and path input is disabled.');
        }

        $inputHelper = null;

        try {
            // Configure the terminal once for the whole answer, even when several prompts are
            // stacked, instead of re-running stty on every round.
            if ($canPaste) {
                $inputHelper = new TerminalInputHelper($inputStream);
                $output->write(self::BPM_ENABLE);
                shell_exec('stty -icanon -echo');
            }

            $read = fn (): array => $canPaste
                ? $this->readWithPasteDetection($inputStream, $output, $question, $inputHelper)
                : $this->readPathInput($inputStream, $question);

            if ($question->isMultiple()) {
                return $this->collectFiles($output, $question, $read, $canPaste);
            }

            $writePrompt();

            $files = $read();

            foreach ($files as $file) {
                $this->assertValid($file);
                $this->displayFile($output, $file);
            }

            return $files;
        } finally {
            if ($canPaste) {
                $output->write(self::BPM_DISABLE);
                $inputHelper?->finish();
            }
        }
    }

    /**
     * Collects several files, keeping the question on a single line and a hint pinned below the
     * growing list of files instead of repeating the prompt for every answer.
     *
     * @param callable():list<InputFile> $read
     *
     * @return list<InputFile>
     */
    private function collectFiles(OutputInterface $output, FileQuestion $question, callable $read, bool $canPaste): array
    {
        $questionLine = ' <info>'.OutputFormatter::escapeTrailingBackslash($question->getQuestion()).'</info>';
        // A "// ..." note, like SymfonyStyle::comment(), reads as a hint rather than a value.
        $hint = ' // Hit Enter to finish';
        $files = [];

        // Redrawing the footer needs a decorated, echo-free (paste) terminal. Otherwise fall back
        // to a plain listing that neither repeats the question nor moves the cursor.
        if (!$canPaste || !$output->isDecorated()) {
            $output->writeln([$questionLine, '', $hint]);

            while ($batch = $read()) {
                foreach ($batch as $file) {
                    $this->assertValid($file);
                    $this->displayFile($output, $file, false, ' ');
                    $files[] = $file;
                }
            }

            return $files;
        }

        $cursor = new Cursor($output);
        $output->writeln($questionLine);

        while (true) {
            // Transient footer, kept one blank line below the files collected so far.
            $output->writeln(['', $hint]);
            $output->write(' > ');

            $batch = $read();

            // Erase the footer (the prompt line, the hint line, and the blank line) before appending.
            $cursor->clearLine()->moveUp()->clearLine()->moveUp()->clearLine()->moveToColumn(1);

            if (!$batch) {
                break;
            }

            foreach ($batch as $file) {
                $this->assertValid($file);
                $this->displayFile($output, $file, false, ' ');
                $files[] = $file;
            }
        }

        return $files;
    }

    private function assertValid(InputFile $file): void
    {
        if (!$file->isValid()) {
            throw new InvalidFileException(\sprintf('File "%s" is not valid or readable.', $file->getPathname()));
        }
    }

    public function displayFile(OutputInterface $output, InputFile $file, bool $thumbnail = true, string $prefix = ''): void
    {
        $path = self::sanitizeForDisplay((string) $file->getRealPath());
        $filename = self::sanitizeForDisplay($file->getFilename());
        $link = \sprintf('<href=file://%s>%s</>', OutputFormatter::escape($path), OutputFormatter::escape($filename));

        if ($output->isVeryVerbose()) {
            $output->writeln(\sprintf('%s<info>%s</info> %s (<comment>%s, %s</comment>)', $prefix, "\u{1F4CE}", $link, OutputFormatter::escape(self::sanitizeForDisplay($file->getMimeType() ?? 'unknown')), $file->getHumanReadableSize()));
        } else {
            $output->writeln(\sprintf('%s<info>%s</info> %s', $prefix, "\u{1F4CE}", $link));
        }

        if ($thumbnail && Terminal::supportsImageProtocol() && $this->isDisplayableImage($file)) {
            $this->displayThumbnail($output, $file);
        }
    }

    /**
     * @param resource $inputStream
     *
     * @return list<InputFile>
     */
    private function readWithPasteDetection($inputStream, OutputInterface $output, FileQuestion $question, TerminalInputHelper $inputHelper): array
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

                return [InputFile::fromData($decoded['data'], $decoded['format'])];
            }

            if ([] !== $files = $this->extractPaths($pasteBuffer, $question)) {
                return $files;
            }
        }

        if ([] !== $files = $this->extractPaths($buffer, $question)) {
            return $files;
        }

        // An empty answer ends the collection of a multiple question.
        if ($question->isMultiple()) {
            return [];
        }

        throw new MissingInputException('No file input provided.');
    }

    /**
     * @param resource $inputStream
     *
     * @return list<InputFile>
     */
    private function readPathInput($inputStream, FileQuestion $question): array
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

        if ([] !== $files = $this->extractPaths($path, $question)) {
            return $files;
        }

        // An empty answer ends the collection of a multiple question.
        if ($question->isMultiple()) {
            return [];
        }

        throw new MissingInputException('No file path provided.');
    }

    /**
     * Turns a raw answer into the files it references: a single path for a regular question, or
     * one file per whitespace-separated path for a multiple question.
     *
     * @return list<InputFile>
     */
    private function extractPaths(string $raw, FileQuestion $question): array
    {
        if (!$question->isPathAllowed()) {
            return [];
        }

        if (!$question->isMultiple()) {
            $path = trim($raw);

            return '' === $path ? [] : [InputFile::fromPath($path)];
        }

        $files = [];
        foreach (self::tokenizePaths($raw) as $path) {
            $files[] = InputFile::fromPath($path);
        }

        return $files;
    }

    /**
     * Splits an answer into individual paths, honoring shell-style quoting and escaping so a
     * single drag&drop of several files - which the terminal inserts as space-separated, quoted
     * or backslash-escaped paths - yields one entry per file.
     *
     * Quotes and escapes are kept in the returned tokens; InputFile::fromPath() normalizes each.
     *
     * @return list<string>
     */
    private static function tokenizePaths(string $input): array
    {
        $tokens = [];
        $current = '';
        $inToken = false;
        $quote = null;
        $length = \strlen($input);

        for ($i = 0; $i < $length; ++$i) {
            $char = $input[$i];

            if (null !== $quote) {
                $current .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ('\\' === $char && '\\' !== \DIRECTORY_SEPARATOR && $i + 1 < $length) {
                $current .= $char.$input[++$i];
                $inToken = true;
                continue;
            }

            if ("'" === $char || '"' === $char) {
                $quote = $char;
                $current .= $char;
                $inToken = true;
                continue;
            }

            if (' ' === $char || "\t" === $char || "\n" === $char || "\r" === $char) {
                if ($inToken) {
                    $tokens[] = $current;
                    $current = '';
                    $inToken = false;
                }
                continue;
            }

            $current .= $char;
            $inToken = true;
        }

        if ($inToken) {
            $tokens[] = $current;
        }

        return $tokens;
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
