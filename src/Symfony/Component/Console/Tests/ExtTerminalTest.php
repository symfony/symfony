<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;

class ExtTerminalTest extends TestCase
{
    #[RequiresPhpExtension('terminal', '>= 1.0.0')]
    public function testDimensionsFromNativeTerminal()
    {
        $this->assertSame([113, 37], $this->runTerminal('dimensions'));
    }

    public function testUnavailableOrOlderExtensionFallsBack()
    {
        if (\extension_loaded('terminal') && version_compare(phpversion('terminal'), '1.0.0', '>=')) {
            $this->markTestSkipped('Requires no ext-terminal or a pre-1.0 version.');
        }

        $this->assertSame([80, 50], $this->runTerminal('compatibility'));
        $this->assertSame(['value' => 'secret', 'output' => "Password: \n"], $this->runTerminal('compatibility-hidden', "secret\n"));
    }

    #[TestWith(['hidden', " secret\n", 'secret'])]
    #[TestWith(['untrimmed', " secret \n", ' secret '])]
    #[TestWith(['hidden', "\n", null])]
    #[RequiresPhpExtension('terminal', '>= 1.0.0')]
    public function testHiddenResponseUsesOutputInterface(string $scenario, string $input, ?string $expected)
    {
        $this->assertSame(['value' => $expected, 'output' => "Password: \n"], $this->runTerminal($scenario, $input));
    }

    #[TestWith(["\x03"])]
    #[TestWith(["\x04"])]
    #[TestWith(["\x1b"])]
    #[RequiresPhpExtension('terminal', '>= 1.0.0')]
    public function testCancellationWithoutFallback(string $key)
    {
        $this->assertSame([
            'exception' => RuntimeException::class,
            'previous' => \RuntimeException::class,
            'output' => 'Password: ',
        ], $this->runTerminal('hidden', 'secret'.$key));
    }

    #[TestWith(["\x03"])]
    #[TestWith(["\x04"])]
    #[TestWith(["\x1b"])]
    #[RequiresPhpExtension('terminal', '>= 1.0.0')]
    public function testCancellationWithFallback(string $key)
    {
        $this->assertSame(['value' => 'visible', 'output' => 'Password: '], $this->runTerminal('fallback', 'secret'.$key));
    }

    #[RequiresPhpExtension('terminal', '>= 1.0.0')]
    public function testDisableSttyPreventsNativeHiddenInput()
    {
        $this->assertSame([
            'exception' => RuntimeException::class,
            'previous' => null,
            'output' => 'Password: ',
        ], $this->runTerminal('disabled'));
    }

    private function runTerminal(string $scenario, ?string $input = null): array
    {
        if ('\\' === \DIRECTORY_SEPARATOR || !\function_exists('proc_open')) {
            $this->markTestSkipped('POSIX pseudo terminals are required.');
        }

        $ttyFd = match ($scenario) {
            'dimensions' => 1,
            'disabled' => 0,
            default => 3,
        };
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => ['pipe', 'r'], 4 => ['pipe', 'r']];
        $descriptors[$ttyFd] = ['pty'];
        $process = @proc_open([\PHP_BINARY, __DIR__.'/Fixtures/terminal_extension.php', $scenario], $descriptors, $pipes);
        if (!\is_resource($process)) {
            $this->markTestSkipped('Cannot allocate a pseudo terminal.');
        }

        $output = $error = $echo = '';
        $sent = false;
        try {
            $this->stty($pipes[$ttyFd], ['cols', '113', 'rows', '37']);
            $before = $this->stty($pipes[$ttyFd], ['-g']);
            foreach ([1, 2, $ttyFd] as $fd) {
                stream_set_blocking($pipes[$fd], false);
            }
            fwrite($pipes[4], "go\n");
            $deadline = microtime(true) + 5;
            do {
                $output .= 1 === $ttyFd ? $this->readPty($pipes[1]) : stream_get_contents($pipes[1]);
                $error .= stream_get_contents($pipes[2]);
                if (1 !== $ttyFd) {
                    $echo .= $this->readPty($pipes[$ttyFd]);
                }
                if (!$sent && null !== $input && str_contains($output, "READY\n") && ('compatibility-hidden' === $scenario || $before !== $this->stty($pipes[$ttyFd], ['-g']))) {
                    fwrite($pipes[$ttyFd], $input);
                    $sent = true;
                } elseif ($sent && 'fallback' === $scenario && !str_contains($this->stty($pipes[$ttyFd], ['-a']), '-icanon')) {
                    fwrite($pipes[$ttyFd], "visible\n");
                    $input = null;
                    $sent = false;
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            if ($status['running']) {
                proc_terminate($process, 9);
                $this->fail('Terminal child did not finish: '.$output.$error);
            }
            $output .= 1 === $ttyFd ? $this->readPty($pipes[1]) : stream_get_contents($pipes[1]);
            $error .= stream_get_contents($pipes[2]);
            $this->assertSame(0, $status['exitcode'], $output.$error);
            $this->assertSame('', $error);
            if (1 !== $ttyFd) {
                $echo .= $this->readPty($pipes[$ttyFd]);
            }
            if ('compatibility-hidden' === $scenario) {
                $this->assertContains($echo, ['', "secret\n", "secret\r\n"]);
            } elseif ('fallback' === $scenario) {
                $this->assertContains($echo, ['', "visible\n", "visible\r\n"]);
            } else {
                $this->assertSame('', $echo, 'Hidden input must not echo to the TTY.');
            }

            return json_decode(str_replace("READY\n", '', $output), true, flags: \JSON_THROW_ON_ERROR);
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    private function readPty($stream): string
    {
        // Linux reports EIO when the last PTY slave closes.
        set_error_handler(static fn ($severity, $message) => str_contains($message, 'errno=5 '));
        try {
            return (string) stream_get_contents($stream);
        } finally {
            restore_error_handler();
        }
    }

    private function stty($stream, array $arguments): string
    {
        $process = proc_open(['stty', ...$arguments], [0 => $stream, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);

        return $output;
    }
}
