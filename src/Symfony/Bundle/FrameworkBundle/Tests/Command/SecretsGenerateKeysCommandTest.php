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
    private const OLD_DEC_KEY_FILE = 'test.decrypt.private.old.php';

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

    public function testRotateReencryptsSecretsWithTheNewKeys()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('DATABASE_URL', 'mysql://app:secret@localhost/db');
        $vault->seal('STRIPE_SECRET', 'sk_live_123');

        $privateKey = file_get_contents($this->secretsDir.'/'.self::DEC_KEY_FILE);
        $publicKey = file_get_contents($this->secretsDir.'/'.self::ENC_KEY_FILE);
        $databaseFile = $this->secretPath('DATABASE_URL');
        $stripeFile = $this->secretPath('STRIPE_SECRET');
        $database = file_get_contents($databaseFile);
        $stripe = file_get_contents($stripeFile);

        $tester = new CommandTester(new SecretsGenerateKeysCommand($vault));

        $this->assertSame(0, $tester->execute(['--rotate' => true]));
        $this->assertStringContainsString('Existing secrets have been rotated to the new keys.', $tester->getDisplay());
        $this->assertStringNotEqualsFile($this->secretsDir.'/'.self::DEC_KEY_FILE, $privateKey);
        $this->assertStringNotEqualsFile($this->secretsDir.'/'.self::ENC_KEY_FILE, $publicKey);
        $this->assertStringNotEqualsFile($databaseFile, $database);
        $this->assertStringNotEqualsFile($stripeFile, $stripe);
        $this->assertStringContainsString('SYMFONY_DECRYPTION_SECRET=', file_get_contents($this->secretsDir.'/'.self::DEC_KEY_FILE));
        $this->assertSame('mysql://app:secret@localhost/db', $vault->reveal('DATABASE_URL'));
        $this->assertSame('sk_live_123', $vault->reveal('STRIPE_SECRET'));

        $fresh = new SodiumVault($this->secretsDir);
        $this->assertSame(['DATABASE_URL' => 'mysql://app:secret@localhost/db', 'STRIPE_SECRET' => 'sk_live_123'], $fresh->list(true));
        $this->assertFileDoesNotExist($this->secretsDir.'/'.self::OLD_DEC_KEY_FILE);
    }

    public function testInterruptedRotationLeavesEverySecretDecryptable()
    {
        $vault = new class($this->secretsDir) extends SodiumVault {
            public bool $interrupt = false;

            public function seal(string $name, string $value): void
            {
                parent::seal($name, $value);

                if ($this->interrupt) {
                    throw new \RuntimeException('interrupted');
                }
            }
        };
        $vault->generateKeys();
        $vault->seal('DATABASE_URL', 'mysql://app:secret@localhost/db');
        $vault->seal('STRIPE_SECRET', 'sk_live_123');

        $privateKey = file_get_contents($this->secretsDir.'/'.self::DEC_KEY_FILE);
        $list = file_get_contents($this->secretsDir.'/test.list.php');
        $vault->interrupt = true;

        try {
            (new CommandTester(new SecretsGenerateKeysCommand($vault)))->execute(['--rotate' => true]);
            $this->fail('Rotation should have been interrupted.');
        } catch (\RuntimeException $e) {
            $this->assertSame('interrupted', $e->getMessage());
        }

        $this->assertSame('mysql://app:secret@localhost/db', $vault->reveal('DATABASE_URL'));
        $this->assertSame('sk_live_123', $vault->reveal('STRIPE_SECRET'));
        $this->assertSame(['DATABASE_URL' => 'mysql://app:secret@localhost/db', 'STRIPE_SECRET' => 'sk_live_123'], (new SodiumVault($this->secretsDir))->list(true));
        $this->assertStringEqualsFile($this->secretsDir.'/test.list.php', $list);
        $this->assertFileExists($this->secretsDir.'/'.self::OLD_DEC_KEY_FILE);

        $vault->interrupt = false;

        $this->assertSame(0, (new CommandTester(new SecretsGenerateKeysCommand($vault)))->execute(['--rotate' => true]));
        $this->assertFileDoesNotExist($this->secretsDir.'/'.self::OLD_DEC_KEY_FILE);
        $this->assertStringNotEqualsFile($this->secretsDir.'/'.self::DEC_KEY_FILE, $privateKey);

        $newKey = include $this->secretsDir.'/'.self::DEC_KEY_FILE;
        $this->assertSame(['DATABASE_URL' => 'mysql://app:secret@localhost/db', 'STRIPE_SECRET' => 'sk_live_123'], (new SodiumVault($this->secretsDir, $newKey))->list(true));
    }

    public function testSecretsCanBeReadWhileRotating()
    {
        $vault = new class($this->secretsDir) extends SodiumVault {
            public ?string $readerDir = null;
            public ?array $read = null;

            public function seal(string $name, string $value): void
            {
                parent::seal($name, $value);

                if ($dir = $this->readerDir) {
                    $this->readerDir = null;
                    $this->read = (new SodiumVault($dir))->list(true);
                }
            }
        };
        $vault->generateKeys();
        $vault->seal('DATABASE_URL', 'mysql://app:secret@localhost/db');
        $vault->seal('STRIPE_SECRET', 'sk_live_123');
        $vault->readerDir = $this->secretsDir;

        $this->assertSame(0, (new CommandTester(new SecretsGenerateKeysCommand($vault)))->execute(['--rotate' => true]));
        $this->assertSame(['DATABASE_URL' => 'mysql://app:secret@localhost/db', 'STRIPE_SECRET' => 'sk_live_123'], $vault->read);
    }

    public function testRotateKeepsFilePermissionsAndSymlinks()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('File permissions and symlinks behave differently on Windows.');
        }

        $vault = new class($this->secretsDir) extends SodiumVault {
            public ?string $oldKeyFile = null;
            public ?int $oldKeyMode = null;

            public function seal(string $name, string $value): void
            {
                parent::seal($name, $value);

                if ($this->oldKeyFile && is_file($this->oldKeyFile)) {
                    $this->oldKeyMode = fileperms($this->oldKeyFile) & 0o777;
                }
            }
        };
        $vault->generateKeys();
        $vault->seal('STRIPE_SECRET', 'sk_live_123');

        $keyFile = $this->secretsDir.'/keys/private.php';
        mkdir(\dirname($keyFile));
        rename($this->secretsDir.'/'.self::DEC_KEY_FILE, $keyFile);
        symlink($keyFile, $this->secretsDir.'/'.self::DEC_KEY_FILE);
        chmod($keyFile, 0o600);
        chmod($secretFile = $this->secretPath('STRIPE_SECRET'), 0o640);
        $vault->oldKeyFile = $this->secretsDir.'/'.self::OLD_DEC_KEY_FILE;

        $this->assertSame(0, (new CommandTester(new SecretsGenerateKeysCommand($vault)))->execute(['--rotate' => true]));

        clearstatcache();
        $this->assertSame(0o600, $vault->oldKeyMode);
        $this->assertTrue(is_link($this->secretsDir.'/'.self::DEC_KEY_FILE));
        $this->assertSame(0o600, fileperms($keyFile) & 0o777);
        $this->assertSame(0o640, fileperms($secretFile) & 0o777);
        $this->assertSame('sk_live_123', (new SodiumVault($this->secretsDir, include $keyFile))->reveal('STRIPE_SECRET'));
    }

    public function testRotateGeneratesKeysThenSealsSecretsWithAnyVault()
    {
        $vault = new class extends AbstractVault {
            public bool $canGenerateKeys = false;
            public array $sealed = [];

            public function generateKeys(bool $override = false): bool
            {
                $this->lastMessage = $this->canGenerateKeys ? 'Keys generated.' : 'Keys not generated.';

                return $this->canGenerateKeys;
            }

            public function seal(string $name, string $value): void
            {
                $this->sealed[$name] = $value;
                $this->lastMessage = 'Secret sealed.';
            }

            public function reveal(string $name): ?string
            {
                return ['FOO' => 'foo', 'BAR' => 'bar'][$name] ?? null;
            }

            public function remove(string $name): bool
            {
                return false;
            }

            public function list(bool $reveal = false): array
            {
                return $reveal ? ['FOO' => 'foo', 'BAR' => 'bar'] : ['FOO' => null, 'BAR' => null];
            }
        };
        $tester = new CommandTester(new SecretsGenerateKeysCommand($vault));

        $this->assertSame(1, $tester->execute(['--rotate' => true]));
        $this->assertStringContainsString('Keys not generated.', $tester->getDisplay());
        $this->assertSame([], $vault->sealed);

        $vault->canGenerateKeys = true;

        $this->assertSame(0, $tester->execute(['--rotate' => true]));
        $this->assertStringContainsString('Keys generated.', $tester->getDisplay());
        $this->assertStringContainsString('Existing secrets have been rotated to the new keys.', $tester->getDisplay());
        $this->assertSame(['FOO' => 'foo', 'BAR' => 'bar'], $vault->sealed);
    }

    public function testRotateWithDecryptionKeyDoesNotChangeTheVault()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('STRIPE_SECRET', 'sk_live_123');

        $privateKey = file_get_contents($this->secretsDir.'/'.self::DEC_KEY_FILE);
        $secret = file_get_contents($this->secretPath('STRIPE_SECRET'));
        $decryptionKey = include $this->secretsDir.'/'.self::DEC_KEY_FILE;
        $locked = new SodiumVault($this->secretsDir, $decryptionKey);
        $tester = new CommandTester(new SecretsGenerateKeysCommand($locked));

        $this->assertSame(1, $tester->execute(['--rotate' => true]));
        $this->assertStringContainsString('Cannot generate keys when a decryption key has been provided', $tester->getDisplay());
        $this->assertStringEqualsFile($this->secretsDir.'/'.self::DEC_KEY_FILE, $privateKey);
        $this->assertStringEqualsFile($this->secretPath('STRIPE_SECRET'), $secret);
        $this->assertSame('sk_live_123', (new SodiumVault($this->secretsDir))->reveal('STRIPE_SECRET'));
        $this->assertFileDoesNotExist($this->secretsDir.'/'.self::OLD_DEC_KEY_FILE);
    }

    public function testRotateWithEmptyDecryptionKeyStillRotates()
    {
        $vault = new SodiumVault($this->secretsDir, '');
        $vault->generateKeys();
        $vault->seal('STRIPE_SECRET', 'sk_live_123');

        $privateKey = file_get_contents($this->secretsDir.'/'.self::DEC_KEY_FILE);
        $tester = new CommandTester(new SecretsGenerateKeysCommand($vault));

        $this->assertSame(0, $tester->execute(['--rotate' => true]));
        $this->assertStringNotEqualsFile($this->secretsDir.'/'.self::DEC_KEY_FILE, $privateKey);
        $this->assertSame('sk_live_123', (new SodiumVault($this->secretsDir, ''))->reveal('STRIPE_SECRET'));
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

    private function secretPath(string $name): string
    {
        return $this->secretsDir.'/test.'.$name.'.'.substr(md5($name), 0, 6).'.php';
    }
}
