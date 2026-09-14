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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Command\GenerateDataKeyCommand;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\EncryptOnlyKms;

class GenerateDataKeyCommandTest extends TestCase
{
    private InMemoryKms $kms;

    protected function setUp(): void
    {
        $this->kms = new InMemoryKms();
    }

    public function testOutputIsJsonWithBothFormsAndMetadata()
    {
        $tester = $this->tester(['default' => $this->kms]);
        $tester->execute(['key-id' => 'app']);
        $tester->assertCommandIsSuccessful();

        $payload = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame('app', $payload['key_id']);
        $this->assertSame(32, $payload['length']);
        $this->assertSame(32, \strlen(base64_decode($payload['plaintext'], true)));
        $this->assertNotEmpty($payload['wrapped']);
    }

    public function testWrappedFormDecryptsBackToThePlaintext()
    {
        $tester = $this->tester(['default' => $this->kms]);
        $tester->execute(['key-id' => 'app']);
        $tester->assertCommandIsSuccessful();

        $payload = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $plaintext = base64_decode($payload['plaintext'], true);
        $wrapped = new Ciphertext(base64_decode($payload['wrapped'], true), 'app');

        $this->assertSame($plaintext, $this->kms->decrypt($wrapped));
    }

    public function testCustomLengthIsHonored()
    {
        $tester = $this->tester(['default' => $this->kms]);
        $tester->execute(['key-id' => 'app', '--length' => '64']);
        $tester->assertCommandIsSuccessful();

        $payload = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame(64, $payload['length']);
        $this->assertSame(64, \strlen(base64_decode($payload['plaintext'], true)));
    }

    public function testTooShortLengthIsRejected()
    {
        $tester = $this->tester(['default' => $this->kms]);
        $exit = $tester->execute(['key-id' => 'app', '--length' => '8']);

        $this->assertSame(Command::INVALID, $exit);
        $this->assertStringContainsString('at least 16 bytes', $tester->getDisplay());
    }

    public function testAadIsBoundToTheWrappedForm()
    {
        $tester = $this->tester(['default' => $this->kms]);
        $tester->execute(['key-id' => 'app', '--aad' => 'tenant=acme']);
        $tester->assertCommandIsSuccessful();

        $payload = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $wrapped = new Ciphertext(base64_decode($payload['wrapped'], true), 'app');

        $this->assertSame(base64_decode($payload['plaintext'], true), $this->kms->decrypt($wrapped, 'tenant=acme'));
    }

    public function testKmsWithoutDataKeyCapabilityIsRejected()
    {
        $tester = new CommandTester(new GenerateDataKeyCommand(self::locator(['default' => new EncryptOnlyKms()])));
        $exit = $tester->execute(['key-id' => 'app']);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('does not support data-key generation', preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * @param array<string, EncrypterInterface&DecrypterInterface> $clients
     */
    private function tester(array $clients): CommandTester
    {
        return new CommandTester(new GenerateDataKeyCommand(self::locator($clients)));
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
