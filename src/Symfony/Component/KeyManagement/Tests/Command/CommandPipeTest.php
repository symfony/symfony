<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Command;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the commands as separate processes joined by a real shell pipe.
 *
 * A CommandTester would only prove that the wiring holds inside a single process: it substitutes
 * its own in-memory streams for STDIN and STDOUT, so it cannot catch a payload mangled on its way
 * through an actual pipe (a stray newline, a formatted style tag, a diagnostic written to the
 * payload stream). These tests spawn the console twice and let the operating system connect them.
 */
#[RequiresPhpExtension('openssl')]
class CommandPipeTest extends TestCase
{
    private const CONSOLE = __DIR__.'/../Fixtures/console.php';

    private string $php;
    private string $key;

    protected function setUp(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('The pipeline requires a POSIX shell.');
        }

        $this->php = (new PhpExecutableFinder())->find(false) ?: 'php';
        $this->key = base64_encode(random_bytes(32));
    }

    public function testEncryptPipedIntoDecryptRestoresThePayload()
    {
        $output = $this->pipe("hello world\n", 'key-management:encrypt app', 'key-management:decrypt');

        $this->assertSame("hello world\n", $output);
    }

    /**
     * The scenario raised in the review: re-encrypt an existing envelope by piping the decryption
     * into a fresh encryption.
     */
    public function testDecryptPipedIntoEncryptReEncryptsThePayload()
    {
        $payload = 'a secret worth rotating';
        $envelope = $this->capture($payload, 'key-management:encrypt app');

        $reEncrypted = $this->pipe($envelope, 'key-management:decrypt', 'key-management:encrypt app');

        $this->assertNotSame(trim($envelope), trim($reEncrypted), 'each encryption draws a fresh data key and nonce.');
        $this->assertSame($payload, $this->capture($reEncrypted, 'key-management:decrypt'));
    }

    public function testBinaryPayloadSurvivesTheRoundTrip()
    {
        $payload = "binary\x00\xFF\x1b[31mred\x1b[0m <info>not a style tag</info>\n\n";

        $output = $this->pipe($payload, 'key-management:encrypt app', 'key-management:decrypt');

        $this->assertSame($payload, $output);
    }

    public function testAadTravelsAcrossThePipeline()
    {
        $payload = 'bound to a tenant';
        $aad = 'tenant=acme;env=prod';

        $output = $this->pipe($payload, \sprintf('key-management:encrypt app --aad %s', escapeshellarg($aad)), \sprintf('key-management:decrypt --aad %s', escapeshellarg($aad)));

        $this->assertSame($payload, $output);
    }

    public function testDiagnosticsStayOutOfThePipeline()
    {
        $process = $this->process(\sprintf('%s key-management:decrypt', $this->console()));
        $process->setInput('$$$ not an envelope $$$');
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame('', $process->getOutput(), 'a downstream command must not receive the error block as its payload.');
        $this->assertStringContainsString('not valid base64', $process->getErrorOutput());
    }

    private function pipe(string $input, string $upstream, string $downstream): string
    {
        $process = $this->process(\sprintf('%s %s | %s %s', $this->console(), $upstream, $this->console(), $downstream));
        $process->setInput($input);
        $process->mustRun();

        return $process->getOutput();
    }

    private function capture(string $input, string $command): string
    {
        $process = $this->process(\sprintf('%s %s', $this->console(), $command));
        $process->setInput($input);
        $process->mustRun();

        return $process->getOutput();
    }

    private function process(string $commandline): Process
    {
        $process = Process::fromShellCommandline($commandline);
        $process->setEnv(['KMS_TEST_KEY' => $this->key]);

        return $process;
    }

    private function console(): string
    {
        return escapeshellarg($this->php).' '.escapeshellarg(self::CONSOLE);
    }
}
