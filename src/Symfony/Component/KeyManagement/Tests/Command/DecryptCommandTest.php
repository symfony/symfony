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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Command\DecryptCommand;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;

#[RequiresPhpExtension('openssl')]
class DecryptCommandTest extends TestCase
{
    private OpenSslKms $primary;
    private OpenSslKms $secondary;

    protected function setUp(): void
    {
        $this->primary = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $this->secondary = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
    }

    public function testRoundTripWithSingleClient()
    {
        $envelope = base64_encode((string) (new EnvelopeEncrypter($this->primary))->encrypt('app', 'hello'));

        $tester = $this->tester(['default' => $this->primary]);
        $tester->execute(['envelope' => $envelope]);
        $tester->assertCommandIsSuccessful();

        $this->assertSame('hello', $tester->getDisplay());
    }

    public function testBytesPerfectRoundTrip()
    {
        $payload = "binary\x00\xFF\nplaintext";
        $envelope = base64_encode((string) (new EnvelopeEncrypter($this->primary))->encrypt('app', $payload));

        $tester = $this->tester(['default' => $this->primary]);
        $tester->execute(['envelope' => $envelope]);
        $tester->assertCommandIsSuccessful();

        $this->assertSame($payload, $tester->getDisplay(), 'decrypt output must be byte-faithful: no trailing newline appended.');
    }

    public function testKmsOptionPicksTheNamedClient()
    {
        $envelope = base64_encode((string) (new EnvelopeEncrypter($this->secondary))->encrypt('app', 'hello'));

        $tester = $this->tester(['primary' => $this->primary, 'aws' => $this->secondary]);
        $tester->execute(['envelope' => $envelope, '--client' => 'aws']);
        $tester->assertCommandIsSuccessful();

        $this->assertSame('hello', $tester->getDisplay());
    }

    public function testAadMustMatchEncryption()
    {
        $envelope = base64_encode((string) (new EnvelopeEncrypter($this->primary))->encrypt('app', 'hello', 'tenant=acme'));

        $tester = $this->tester(['default' => $this->primary]);
        $exit = $tester->execute([
            'envelope' => $envelope,
            '--aad' => 'tenant=globex',
        ]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('Decryption failed.', $tester->getDisplay());
    }

    public function testInvalidBase64IsRejected()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $exit = $tester->execute(['envelope' => '$$$not-base64$$$']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('not valid base64', $tester->getDisplay());
    }

    public function testMalformedEnvelopeIsRejected()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $exit = $tester->execute(['envelope' => base64_encode('not a real envelope')]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('malformed', $tester->getDisplay());
    }

    public function testAStoredEnvelopeIsRefusedWithAnActionableMessage()
    {
        $stored = (new StoredEnvelopeEncrypter(new InMemoryDataKeyStore()))->encrypt('user.email', 'hello');

        $tester = $this->tester(['default' => $this->primary]);
        $exit = $tester->execute(['envelope' => base64_encode((string) $stored)]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('held in a store', $tester->getDisplay());
    }

    public function testEmptyLocatorFailsLoudly()
    {
        $tester = $this->tester([]);
        $exit = $tester->execute(['envelope' => 'AAAA']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('No KMS service is registered.', $tester->getDisplay());
    }

    public function testEnvelopeIsReadFromTheInputStreamWhenTheArgumentIsOmitted()
    {
        $payload = "binary\x00\xFF\npiped plaintext\n\n";
        $envelope = base64_encode((string) (new EnvelopeEncrypter($this->primary))->encrypt('app', $payload));
        $command = new Command(null, new DecryptCommand(self::locator(['default' => $this->primary])));
        $input = new ArrayInput([]);
        $input->setStream(self::stream($envelope."\n"));
        $output = new BufferedOutput();

        $this->assertSame(Command::SUCCESS, $command->run($input, $output));
        $this->assertSame($payload, $output->fetch(), 'the trailing newline written by key-management:encrypt must be tolerated.');
    }

    public function testDiagnosticsAreWrittenToStderr()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $exit = $tester->execute(['envelope' => '$$$not-base64$$$'], ['capture_stderr_separately' => true]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('not valid base64', $tester->getErrorOutput());
        $this->assertSame('', $tester->getDisplay(), 'diagnostics must not reach the stream carrying the plaintext.');
    }

    /**
     * @param array<string, OpenSslKms> $clients
     */
    private function tester(array $clients): CommandTester
    {
        return new CommandTester(new DecryptCommand(self::locator($clients)));
    }

    /**
     * @return resource
     */
    private static function stream(string $contents)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @param array<string, OpenSslKms> $clients
     */
    private static function locator(array $clients): ServiceLocator
    {
        $factories = [];
        foreach ($clients as $name => $kms) {
            $factories[$name] = static fn () => $kms;
        }

        return new ServiceLocator($factories);
    }
}
