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

        $result .= $textBold.$textBrightWhite."Exception Trace".$resetStyle."\n";
        $result .= $traceAsString."\n";

        $result .= $textBold.$textBrightRed.\get_class($exception).$resetStyle."\n";
        $result .= $exception->getMessage()."\n\n";

        $sourceCode =file($exception->getFile());
        $sourceCodeExtract = [];
        for ($i = $exception->getLine() - 5; $i <= $exception->getLine() + 4; ++$i) {
            $sourceCodeExtract[$i] = ($sourceCode[$i] ?? '')."\n";
        }

        $result .= $textGray.sprintf('at %s:%d', $exception->getFile(), $exception->getLine()).$resetStyle."\n";
        foreach ($sourceCodeExtract as $lineNumber => $code) {
            if ($lineNumber === $exception->getLine()) {
                $result .= sprintf("%s~~~~>%s %s\n", $textBrightRed, $resetStyle, rtrim($code));
            } else {
                $result .= sprintf("%s%3d |%s %s\n", $textGray, $lineNumber, $resetStyle, rtrim($code));
            }
        }
        $result .= "\n\n";

        return $result;
    }
}
