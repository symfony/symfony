<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\ErrorRenderer;

use Symfony\Component\Console\Terminal;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\VarDumper\Dumper\CliDumper;

// Help opcache.preload discover always-needed symbols
class_exists(CliDumper::class);

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CliErrorRenderer implements ErrorRendererInterface
{
    public function render(\Throwable $exception): FlattenException
    {
        return FlattenException::createFromThrowable($exception)->setAsString($this->doRender($exception));
    }

    private function doRender(\Throwable $exception): string
    {
        $result = '';

        $result .= $this->renderExceptionSummary($exception);
        $result .= $this->renderSourceCodeExcerpt($exception);
        $result .= $this->renderExceptionTrace($exception);
        $result .= "\n\n";

        return $result;
    }

    private function renderExceptionSummary(\Throwable $exception): string
    {
        $content = '';

        $exceptionFqcn = \get_class($exception);
        $content .= $this->fullLineWithRedBackground("👻 $exceptionFqcn")."\n";
        $content .= $this->textBrightRed($exception->getMessage())."\n\n";

        return $content;
    }

    private function renderSourceCodeExcerpt(\Throwable $exception): string
    {
        $content = '';

        // using the previous line number generates much more precise results
        $exceptionLineNumber = $exception->getLine() - 1;

        [$exceptionFilePath, $exceptionFileName] = $this->findExceptionFilePathAndName($exception->getFile());
        $exceptionPathInformation = $this->textGray($this->makePathRelative($exceptionFilePath)).$this->textBrightWhite(sprintf('%s:%d', $exceptionFileName, $exceptionLineNumber))."\n";
        $content .= $this->textGray('at ').$this->renderAsLink($exceptionPathInformation, $exception->getFile(), $exceptionLineNumber);

        $sourceCode = file($exception->getFile());
        if (false === $sourceCode) {
            return $content."\n";
        }

        $content .= $this->separatorLine()."\n";

        $sourceCodeExtract = [];
        for ($i = $exception->getLine() - 3; $i <= $exception->getLine() + 3; ++$i) {
            $sourceCodeExtract[$i] = ($sourceCode[$i] ?? '')."\n";
        }

        $maxLineNumberLengthInDigits = \strlen((string) $exceptionLineNumber + 4);
        foreach ($sourceCodeExtract as $lineNumber => $code) {
            $lineNumberFormatted = sprintf("%{$maxLineNumberLengthInDigits}d |", $lineNumber);
            if ($lineNumber === $exceptionLineNumber) {
                $content .= $this->textRedBackground($lineNumberFormatted);
            } else {
                $content .= $this->textGray($lineNumberFormatted);
            }

            $content .= sprintf(" %s\n", rtrim($code));
        }

        return $content;
    }

    private function renderExceptionTrace(\Throwable $exception): string
    {
        $content = '';

        $exceptionFqcn = \get_class($exception);
        if (!$this->shouldTraceBeRenderedForException($exceptionFqcn)) {
            return $content;
        }

        $content .= "\nException Trace\n";
        $content .= $this->separatorLine()."\n";
        foreach ($exception->getTrace() as $frame) {
            $frameHasClass = $frame['class'] ?? false;

            $content .= $this->textGray(' at ');
            if ($frameHasClass) {
                $classPathParts = explode('\\', $frame['class']);
                $content .= $this->textGray(array_pop($classPathParts));
            }

            if ($frame['function'] ?? false) {
                $content .= $frameHasClass ? $this->textGray('::') : '';
                $content .= $frame['function'].'()';
            }

            if ($frame['file'] ?? false) {
                $content .= $this->textGray(' in ');
                $path = $this->textGray($this->makePathRelative($frame['file']));
                $path .= ($frame['line'] ?? false) ? $this->textGray(sprintf(':%d', $frame['line'])) : '';
                $content .= $this->renderAsLink($path, $frame['file'], $frame['line'] ?? 0);
            }

            $content .= "\n";
        }

        return $content;
    }

    private function separatorLine(): string
    {
        $terminalWidth = (new Terminal())->getWidth();

        return $this->textGray(str_repeat('═', $terminalWidth));
    }

    private function fullLineWithRedBackground(string $content): string
    {
        return sprintf('%s%s%s', "\033[97;41m\033[K", $content, "\033[0m");
    }

    private function textRedBackground(string $content): string
    {
        return sprintf('%s%s%s', "\033[97;41m", $content, "\033[0m");
    }

    private function textGray(string $content): string
    {
        return sprintf('%s%s%s', "\033[38;5;245m", $content, "\033[0m");
    }

    private function textRed(string $content): string
    {
        return sprintf('%s%s%s', "\033[31m", $content, "\033[0m");
    }

    private function textBrightRed(string $content): string
    {
        return sprintf('%s%s%s', "\033[91m", $content, "\033[0m");
    }

    private function textBrightWhite(string $content): string
    {
        return sprintf('%s%s%s', "\033[37;1m", $content, "\033[0m");
    }

    private function shouldTraceBeRenderedForException(string $exceptionFqcn): bool
    {
        return !in_array($exceptionFqcn, [\ParseError::class], true);
    }

    private function findExceptionFilePathAndName(string $filepath): array
    {
        $dirPath = pathinfo($filepath, PATHINFO_DIRNAME);
        $fileName = pathinfo($filepath, PATHINFO_BASENAME);

        // if $filepath = /projects/foo/bar.php, $dirPath = /projects/foo and $filename = bar.php
        // in those cases, append a DIRECTORY_SEPARATOR to the $dirPath so you can concatenate
        // both variables later to reconstruct the original $filepath
        if ('' !== $dirPath && '' !== $fileName) {
            $dirPath .= DIRECTORY_SEPARATOR;
        }

        return [$dirPath, $fileName];
    }

    private function makePathRelative(string $absolutePath): string
    {
        $vendorPrefix = str_replace([
            'symfony'.DIRECTORY_SEPARATOR.'error-handler'.DIRECTORY_SEPARATOR.'ErrorRenderer'.DIRECTORY_SEPARATOR.'CliErrorRenderer.php',
            'src'.DIRECTORY_SEPARATOR.'Symfony'.DIRECTORY_SEPARATOR.'Component'.DIRECTORY_SEPARATOR.'ErrorHandler'.DIRECTORY_SEPARATOR.'ErrorRenderer'.DIRECTORY_SEPARATOR.'CliErrorRenderer.php',
        ], '', __FILE__);
        $projectPrefix = \dirname($vendorPrefix).DIRECTORY_SEPARATOR;

        return str_replace($projectPrefix, '', $absolutePath);
    }

    private function renderAsLink(string $content, string $filePath, int $lineNumber): string
    {
        $terminalSupportsLinks = 'JetBrains-JediTerm' !== getenv('TERMINAL_EMULATOR') && (!getenv('KONSOLE_VERSION') || (int) getenv('KONSOLE_VERSION') > 201100);
        $href = sprintf('file://%s#L%d', $filePath, $lineNumber);

        return $terminalSupportsLinks ? "\033]8;;{$href}\033\\{$content}\033]8;;\033\\" : $content;
    }
}
