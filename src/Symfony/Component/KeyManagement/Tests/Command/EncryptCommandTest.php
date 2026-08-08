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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Command\EncryptCommand;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Tests\Fixtures\UnreachableKms;

#[RequiresPhpExtension('openssl')]
class EncryptCommandTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private OpenSslKms $primary;
    private OpenSslKms $secondary;

    protected function setUp(): void
    {
        $this->primary = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $this->secondary = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
    }

    public function testSingleClientIsAutoSelected()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $tester->execute(['key-id' => 'app', 'value' => 'hello']);
        $tester->assertCommandIsSuccessful();

        $this->assertSame('hello', $this->roundTrip($this->primary, $tester));
    }

    public function testKmsOptionPicksTheNamedClient()
    {
        $tester = $this->tester(['primary' => $this->primary, 'aws' => $this->secondary]);
        $tester->execute(['key-id' => 'app', 'value' => 'hello', '--client' => 'aws']);
        $tester->assertCommandIsSuccessful();

        $this->assertSame('hello', $this->roundTrip($this->secondary, $tester));
    }

    public function testDefaultClientIsPreferredWhenNoKmsOption()
    {
        $tester = $this->tester(['default' => $this->primary, 'aws' => $this->secondary]);
        $tester->execute(['key-id' => 'app', 'value' => 'hello']);
        $tester->assertCommandIsSuccessful();

        $this->assertSame('hello', $this->roundTrip($this->primary, $tester));
    }

    public function testAmbiguousSelectionWithoutDefaultIsRejected()
    {
        $tester = $this->tester(['primary' => $this->primary, 'aws' => $this->secondary]);
        $exit = $tester->execute(['key-id' => 'app', 'value' => 'hello']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Several KMS clients are registered', $tester->getDisplay());
    }

    public function testUnknownKmsName()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $exit = $tester->execute(['key-id' => 'app', 'value' => 'hello', '--client' => 'missing']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Unknown KMS client "missing"', $tester->getDisplay());
    }

    public function testEmptyLocatorFailsLoudly()
    {
        $tester = $this->tester([]);
        $exit = $tester->execute(['key-id' => 'app', 'value' => 'hello']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('No KMS service is registered.', $tester->getDisplay());
    }

    public function testAadIsForwardedAsOpaqueBytes()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $tester->execute([
            'key-id' => 'app',
            'value' => 'hello',
            '--aad' => 'tenant=acme;env=prod',
        ]);
        $tester->assertCommandIsSuccessful();

        $this->assertSame('hello', $this->roundTrip($this->primary, $tester, 'tenant=acme;env=prod'));
    }

    public function testEnvelopeEmbedsTheKeyId()
    {
        $tester = $this->tester(['default' => $this->primary]);
        $tester->execute(['key-id' => 'app', 'value' => 'hello']);
        $tester->assertCommandIsSuccessful();

        $envelope = Envelope::fromBytes(base64_decode(trim($tester->getDisplay()), true));
        $this->assertSame('app', $envelope->keyId);
    }

    public function testKmsWithoutDataKeyCapabilityFailsLoudly()
    {
        $tester = new CommandTester(new EncryptCommand(self::locator(['default' => new EncryptOnlyKms()])));
        $exit = $tester->execute(['key-id' => 'app', 'value' => 'hello']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('does not support data-key generation', preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * Scoped to the frame of the command itself: the console invokes it through
     * `\ReflectionFunction::invoke()`, whose own frame holds every resolved parameter in clear, and
     * that one is the Console component's business rather than this one's.
     */
    public function testThePlaintextDoesNotReachStackTraces()
    {
        $tester = new CommandTester(new EncryptCommand(self::locator(['default' => new UnreachableKms()])));

        $trace = self::traceOf(static fn () => $tester->execute(['key-id' => 'app', 'value' => 'the plaintext payload']));

        self::assertRedacted('the plaintext payload', [self::frameOf(EncryptCommand::class, '__invoke', $trace)]);
    }

    public function testPlaintextIsReadFromTheInputStreamWhenTheArgumentIsOmitted()
    {
        $payload = "binary\x00\xFF\npiped plaintext\n\n";
        $command = new Command(null, new EncryptCommand(self::locator(['default' => $this->primary])));
        $input = new ArrayInput(['key-id' => 'app']);
        $input->setStream(self::stream($payload));
        $output = new BufferedOutput();

        $this->assertSame(Command::SUCCESS, $command->run($input, $output));

        $envelope = Envelope::fromBytes(base64_decode(trim($output->fetch()), true));
        $this->assertSame($payload, (new EnvelopeEncrypter($this->primary))->decrypt($envelope), 'the piped plaintext must be encrypted byte for byte.');
    }

    public function testDiagnosticsAreWrittenToStderr()
    {
        $tester = $this->tester(['primary' => $this->primary, 'aws' => $this->secondary]);
        $exit = $tester->execute(['key-id' => 'app', 'value' => 'hello'], ['capture_stderr_separately' => true]);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('Several KMS clients are registered', $tester->getErrorOutput());
        $this->assertSame('', $tester->getDisplay(), 'diagnostics must not reach the stream carrying the envelope.');
    }

    public function testCompleteSuggestsKmsNames()
    {
        $command = new Command(null, new EncryptCommand(self::locator(['primary' => $this->primary, 'aws' => $this->secondary])));
        $tester = new CommandCompletionTester($command);

        $this->assertSame(['primary', 'aws'], $tester->complete(['--client', '']));
    }

    private function roundTrip(OpenSslKms $kms, CommandTester $tester, string $aad = ''): string
    {
        $bytes = base64_decode(trim($tester->getDisplay()), true);
        $envelope = Envelope::fromBytes($bytes);

        return (new EnvelopeEncrypter($kms))->decrypt($envelope, $aad);
    }

    /**
     * @param array<string, EncrypterInterface&DecrypterInterface> $clients
     */
    private function tester(array $clients): CommandTester
    {
        return new CommandTester(new EncryptCommand(self::locator($clients)));
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
     * @param array<string, EncrypterInterface&DecrypterInterface> $clients
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
