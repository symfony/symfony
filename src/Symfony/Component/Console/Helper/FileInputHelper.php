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
        $link = \sprintf('<href=file://%s>%s</>', $file->getRealPath(), $file->getFilename());

        if ($output->isVeryVerbose()) {
            $output->writeln(\sprintf('<info>%s</info> %s (<comment>%s, %s</comment>)', "\u{1F4CE}", $link, $file->getMimeType() ?? 'unknown', $file->getHumanReadableSize()));
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

        while (!feof($inputStream)) {
            $inputHelper->waitForInput();
            $char = fread($inputStream, 1);

            if (false === $char || '' === $char) {
                if ('' === $buffer && '' === $pasteBuffer) {
                    throw new MissingInputException('Aborted.');
                }
                break;
            }

            $buffer .= $char;

            if (!$inPaste && str_ends_with($buffer, self::PASTE_START)) {
                $inPaste = true;
                $buffer = substr($buffer, 0, -\strlen(self::PASTE_START));
                continue;
            }

            if ($inPaste && str_ends_with($buffer, self::PASTE_END)) {
                $pasteBuffer = substr($buffer, 0, -\strlen(self::PASTE_END));
                break;
            }

            if (!$inPaste && ("\n" === $char || "\r" === $char)) {
                $buffer = rtrim($buffer, "\r\n");
                break;
            }
        }

        if ('' !== $pasteBuffer) {
            if (null !== $this->protocol && $this->protocol->detectPastedImage($pasteBuffer)) {
                $decoded = $this->protocol->decode($pasteBuffer);
                if ('' !== $decoded['data']) {
                    return InputFile::fromData($decoded['data'], $decoded['format']);
                }
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
