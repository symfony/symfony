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

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\VarDumper\Cloner\VarCloner;
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
        $cloner = new VarCloner();
        $dumper = new class() extends CliDumper {
            protected function supportsColors(): bool
            {
                $outputStream = $this->outputStream;
                $this->outputStream = fopen('php://stdout', 'w');

                try {
                    return parent::supportsColors();
                } finally {
                    $this->outputStream = $outputStream;
                }
            }
        };

        $flattenException = FlattenException::createFromThrowable($exception);
        $exceptionTraceDump = $dumper->dump($cloner->cloneVar($exception)['trace'], true);

        return $flattenException->setAsString($this->doRender($exception, $exceptionTraceDump));
    }

    private function doRender(\Throwable $exception, string $traceAsString): string
    {
        $resetStyle = "\033[0m";
        $textBold = "\033[1m";
        $textBrightWhite = "\033[37;1m";
        $textBrightRed = "\033[31;1m";
        $textGray = "\033[38;5;245m";

        $result = '';

        $exceptionFqcn = \get_class($exception);
        $result .= '👻 '.$textBold.$textBrightRed.$exceptionFqcn.$resetStyle."\n";
        $result .= $exception->getMessage()."\n\n";

        $sourceCode = file($exception->getFile());
        $sourceCodeExtract = [];
        for ($i = $exception->getLine() - 3; $i <= $exception->getLine() + 3; ++$i) {
            $sourceCodeExtract[$i] = ($sourceCode[$i] ?? '')."\n";
        }

        $exceptionLineNumber = $exception->getLine();
        [$exceptionFilePath, $exceptionFileName] = $this->findExceptionFilePathAndName($exception->getFile());
        $result .= $textGray.sprintf('at %s%s%s:%d', $exceptionFilePath, $textBrightWhite, $exceptionFileName, $exceptionLineNumber).$resetStyle."\n";
        $maxLineNumberLengthInDigits =  strlen((string) $exceptionLineNumber + 4);
        foreach ($sourceCodeExtract as $lineNumber => $code) {
            if ($lineNumber === $exceptionLineNumber) {
                $result .= sprintf("%s".str_repeat(' ', $maxLineNumberLengthInDigits - 1)."==> %s %s\n", $textBrightRed, $resetStyle, rtrim($code));
            } else {
                $result .= sprintf("%s%".$maxLineNumberLengthInDigits."d |%s %s\n", $textGray, $lineNumber, $resetStyle, rtrim($code));
            }
        }

        if ($this->shouldTheExceptionTraceBeIncluded($exceptionFqcn)) {
            $result .= "\n\n";
            $result .= $textBold.$textBrightWhite."Exception Trace".$resetStyle."\n";
            $result .= $traceAsString."\n";
        }

        $result .= "\n\n";

        return $result;
    }

    private function shouldTheExceptionTraceBeIncluded(string $exceptionFqcn): bool
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
}
