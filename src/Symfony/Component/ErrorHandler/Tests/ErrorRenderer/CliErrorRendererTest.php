<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorRenderer;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;

#[BackupGlobals(true)]
class CliErrorRendererTest extends TestCase
{
    #[TestWith([null, 'composer'])]
    #[TestWith(['', 'composer'])]
    #[TestWith(['symfony', 'symfony composer'])]
    #[TestWith(['symfony-local', 'symfony-local composer'])]
    #[TestWith(['0', '0 composer'])]
    public function testComposerHintsUseSymfonyCli(?string $binaryName, string $prefix)
    {
        if (null === $binaryName) {
            unset($_SERVER['SYMFONY_CLI_BINARY_NAME']);
        } else {
            $_SERVER['SYMFONY_CLI_BINARY_NAME'] = $binaryName;
        }

        $message = 'Try running "composer require acme/package". Already suggested "symfony composer require acme/ready".';
        $previousMessage = 'Try running "composer require acme/previous".';
        $previous = new \Error($previousMessage);
        $exception = new \RuntimeException($message, 0, $previous);
        $rendered = (new CliErrorRenderer())->render($exception);
        $output = $rendered->getAsString();
        $this->assertStringContainsString('Try running "'.$prefix.' require acme/package".', $output);
        $this->assertStringContainsString('Try running "'.$prefix.' require acme/previous".', $output);
        $this->assertStringContainsString('"symfony composer require acme/ready"', $output);
        $this->assertStringNotContainsString('symfony symfony composer', $output);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($message, $rendered->getMessage());
        $this->assertSame($previousMessage, $previous->getMessage());
        $this->assertSame($previousMessage, $rendered->getPrevious()->getMessage());
    }

    public function testComposerHintsOnlyChangeExceptionMessages()
    {
        $_SERVER['SYMFONY_CLI_BINARY_NAME'] = 'symfony';
        $exception = new class('Try running "composer require acme/message".') extends \RuntimeException {
            public string $details = 'Try running "composer require acme/property".';
        };
        $output = (new CliErrorRenderer())->render($exception)->getAsString();

        $this->assertStringContainsString('Try running "symfony composer require acme/message".', $output);
        $this->assertStringContainsString('Try running "composer require acme/property".', $output);
    }

    #[TestWith([null])]
    #[TestWith(['symfony'])]
    public function testComposerHintsKeepAnonymousClassNamesNormalized(?string $binaryName)
    {
        if (null === $binaryName) {
            unset($_SERVER['SYMFONY_CLI_BINARY_NAME']);
        } else {
            $_SERVER['SYMFONY_CLI_BINARY_NAME'] = $binaryName;
        }

        $anonymous = new class extends \Exception {
        };
        $exception = new \RuntimeException(\sprintf('Cannot use "%s". Try running "composer require acme/package".', $anonymous::class));
        $output = (new CliErrorRenderer())->render($exception)->getAsString();
        $prefix = $binaryName ? 'symfony composer' : 'composer';

        $this->assertStringContainsString('Cannot use "Exception@anonymous". Try running "'.$prefix.' require acme/package".', $output);
    }
}
