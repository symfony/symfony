<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\SecretsGenerateKeysCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[RequiresPhpExtension('sodium')]
class SecretsGenerateKeysCommandTest extends TestCase
{
    private string $secretsDir;
    private const ENC_KEY_FILE = 'test.encrypt.public.php';
    private const DEC_KEY_FILE = 'test.decrypt.private.php';

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir().'/sf_secrets/test/';
        (new Filesystem())->remove($this->secretsDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->secretsDir);
    }

    public function testItGeneratesSodiumKeys()
    {
        $vault = new SodiumVault($this->secretsDir);
        $tester = new CommandTester(new SecretsGenerateKeysCommand($vault));

        $this->assertSame(0, $tester->execute([]));
        $this->assertKeysExistAndReadable();
    }

    public function testItRotatesSodiumKeysWhenRequested()
    {
        $vault = new SodiumVault($this->secretsDir);
        $tester = new CommandTester(new SecretsGenerateKeysCommand($vault));

        $this->assertSame(0, $tester->execute(['--rotate' => true]));
        $this->assertKeysExistAndReadable();
    }

    public function testItFailsGracefullyWhenLocalVaultIsDisabled()
    {
        $vault = $this->createStub(AbstractVault::class);
        $tester = new CommandTester(new SecretsGenerateKeysCommand($vault));

        $this->assertSame(1, $tester->execute(['--local' => true]));
        $this->assertStringContainsString('The local vault is disabled.', $tester->getDisplay());
    }

    public function testFailsWhenKeysAlreadyExistAndRotateNotPassed()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();

        $command = new SecretsGenerateKeysCommand($vault);
        $tester = new CommandTester($command);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Sodium keys already exist at', $tester->getDisplay());
    }

    private function assertKeysExistAndReadable(): void
    {
        $encPath = $this->secretsDir.'/'.self::ENC_KEY_FILE;
        $decPath = $this->secretsDir.'/'.self::DEC_KEY_FILE;

        $this->assertFileExists($encPath, 'Encryption key file does not exist.');
        $this->assertFileExists($decPath, 'Decryption key file does not exist.');
        $this->assertNotFalse(@file_get_contents($encPath), 'Encryption key file is not readable.');
        $this->assertNotFalse(@file_get_contents($decPath), 'Decryption key file is not readable.');
    }
}
