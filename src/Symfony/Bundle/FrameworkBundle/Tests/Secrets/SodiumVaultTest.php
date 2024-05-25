<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Secrets;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @requires extension sodium
 */
class SodiumVaultTest extends TestCase
{
    private string $secretsDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->secretsDir = sys_get_temp_dir().'/sf_secrets/test/';
        $this->filesystem->remove($this->secretsDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->secretsDir);
    }

    public function testGenerateKeys()
    {
        $vault = new SodiumVault($this->secretsDir);

        $this->assertTrue($vault->generateKeys());
        $this->assertFileExists($this->secretsDir.'/test.encrypt.public.php');
        $this->assertFileExists($this->secretsDir.'/test.decrypt.private.php');

        $encKey = $this->filesystem->readFile($this->secretsDir.'/test.encrypt.public.php');
        $decKey = $this->filesystem->readFile($this->secretsDir.'/test.decrypt.private.php');

        $this->assertFalse($vault->generateKeys());
        $this->assertStringEqualsFile($this->secretsDir.'/test.encrypt.public.php', $encKey);
        $this->assertStringEqualsFile($this->secretsDir.'/test.decrypt.private.php', $decKey);

        $this->assertTrue($vault->generateKeys(true));
        $this->assertStringNotEqualsFile($this->secretsDir.'/test.encrypt.public.php', $encKey);
        $this->assertStringNotEqualsFile($this->secretsDir.'/test.decrypt.private.php', $decKey);
    }

    public function testEncryptAndDecrypt()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();

        $plain = "plain\ntext";

        $vault->seal('foo', $plain);

        $decrypted = $vault->reveal('foo');
        $this->assertSame($plain, $decrypted);

        $this->assertSame(['foo' => null], $vault->list());
        $this->assertSame(['foo' => $plain], $vault->list(true));

        $this->assertTrue($vault->remove('foo'));
        $this->assertFalse($vault->remove('foo'));

        $this->assertSame([], $vault->list());
    }

    public function testDerivedSecretEnvVar()
    {
        $vault = new SodiumVault($this->secretsDir, null, 'MY_SECRET');
        $vault->generateKeys();
        $vault->seal('FOO', 'bar');

        $this->assertSame(['FOO', 'MY_SECRET'], array_keys($vault->loadEnvVars()));
    }
}
