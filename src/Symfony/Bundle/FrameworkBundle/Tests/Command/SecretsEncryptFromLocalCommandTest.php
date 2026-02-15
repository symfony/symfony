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
use Symfony\Bundle\FrameworkBundle\Command\SecretsEncryptFromLocalCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[RequiresPhpExtension('sodium')]
class SecretsEncryptFromLocalCommandTest extends TestCase
{
    private string $vaultDir;
    private string $localVaultDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->vaultDir = sys_get_temp_dir().'/sf_secrets/vault_'.uniqid();
        $this->localVaultDir = sys_get_temp_dir().'/sf_secrets/local_'.uniqid();
        $this->fs = new Filesystem();
        $this->fs->remove([$this->vaultDir, $this->localVaultDir]);
    }

    protected function tearDown(): void
    {
        $this->fs->remove([$this->vaultDir, $this->localVaultDir]);
    }

    public function testFailsWhenLocalVaultIsDisabled()
    {
        $vault = $this->createStub(AbstractVault::class);
        $command = new SecretsEncryptFromLocalCommand($vault, null);
        $tester = new CommandTester($command);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('The local vault is disabled.', $tester->getDisplay());
    }

    public function testEncryptsLocalOverrides()
    {
        $vault = new SodiumVault($this->vaultDir);
        $vault->generateKeys();

        $localVault = new SodiumVault($this->localVaultDir);
        $localVault->generateKeys();

        $vault->seal('MY_SECRET', 'prod-value');
        $localVault->seal('MY_SECRET', 'local-value');

        $command = new SecretsEncryptFromLocalCommand($vault, $localVault);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);
        $this->assertSame(0, $exitCode);

        $revealed = $vault->reveal('MY_SECRET');
        $this->assertSame('local-value', $revealed);
    }

    public function testDoesNotSealIfSameValue()
    {
        $vault = new SodiumVault($this->vaultDir);
        $vault->generateKeys();

        $localVault = new SodiumVault($this->localVaultDir);
        $localVault->generateKeys();

        $vault->seal('SHARED_SECRET', 'same-value');
        $localVault->seal('SHARED_SECRET', 'same-value');

        $command = new SecretsEncryptFromLocalCommand($vault, $localVault);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);
        $this->assertSame(0, $exitCode);

        $revealed = $vault->reveal('SHARED_SECRET');
        $this->assertSame('same-value', $revealed);
    }

    public function testStillSucceedsIfLocalSecretIsMissing()
    {
        $vault = new SodiumVault($this->vaultDir);
        $vault->generateKeys();

        $localVault = new SodiumVault($this->localVaultDir);
        $localVault->generateKeys();

        $vault->seal('MISSING_IN_LOCAL', 'prod-only');

        $command = new SecretsEncryptFromLocalCommand($vault, $localVault);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute([]));
        $revealed = $vault->reveal('MISSING_IN_LOCAL');
        $this->assertSame('prod-only', $revealed);
    }
}
