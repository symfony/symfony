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
use Symfony\Bundle\FrameworkBundle\Command\SecretsDecryptToLocalCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[RequiresPhpExtension('sodium')]
class SecretsDecryptToLocalCommandTest extends TestCase
{
    private string $mainDir;
    private string $localDir;

    protected function setUp(): void
    {
        $this->mainDir = sys_get_temp_dir().'/sf_secrets/main/';
        $this->localDir = sys_get_temp_dir().'/sf_secrets/local/';

        $fs = new Filesystem();
        $fs->remove([$this->mainDir, $this->localDir]);

        $mainVault = new SodiumVault($this->mainDir);
        $mainVault->generateKeys();
        $mainVault->seal('FOO_SECRET', 'super_secret_value');

        $localVault = new SodiumVault($this->localDir);
        $localVault->generateKeys();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->mainDir, $this->localDir]);
    }

    public function testSecretsAreDecryptedAndStoredInLocalVault()
    {
        $mainVault = new SodiumVault($this->mainDir);
        $localVault = new SodiumVault($this->localDir);
        $tester = new CommandTester(new SecretsDecryptToLocalCommand($mainVault, $localVault));

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('1 secret found in the vault.', $tester->getDisplay());
        $this->assertStringContainsString('Secret "FOO_SECRET" encrypted', $tester->getDisplay());

        $this->assertArrayHasKey('FOO_SECRET', $localVault->list(true));
        $this->assertSame('super_secret_value', $localVault->reveal('FOO_SECRET'));
    }

    public function testExistingLocalSecretsAreSkippedWithoutForce()
    {
        $mainVault = new SodiumVault($this->mainDir);
        $localVault = new SodiumVault($this->localDir);
        $localVault->seal('FOO_SECRET', 'old_value');
        $tester = new CommandTester(new SecretsDecryptToLocalCommand($mainVault, $localVault));

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('1 secret is already overridden in the local vault and will be skipped.', $tester->getDisplay());
        $this->assertSame('old_value', $localVault->reveal('FOO_SECRET'));
    }

    public function testForceOptionOverridesLocalSecrets()
    {
        $mainVault = new SodiumVault($this->mainDir);
        $localVault = new SodiumVault($this->localDir);
        $localVault->seal('FOO_SECRET', 'old_value');
        $tester = new CommandTester(new SecretsDecryptToLocalCommand($mainVault, $localVault));

        $this->assertSame(0, $tester->execute(['--force' => true]));
        $this->assertStringContainsString('Secret "FOO_SECRET" encrypted', $tester->getDisplay());
        $this->assertSame('super_secret_value', $localVault->reveal('FOO_SECRET'));
    }

    public function testFailsGracefullyWhenLocalVaultIsDisabled()
    {
        $mainVault = new SodiumVault($this->mainDir);
        $tester = new CommandTester(new SecretsDecryptToLocalCommand($mainVault, null));

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('The local vault is disabled.', $tester->getDisplay());
    }
}
