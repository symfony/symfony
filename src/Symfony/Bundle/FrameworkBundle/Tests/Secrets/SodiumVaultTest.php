<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Secrets;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Filesystem\Filesystem;

class SodiumVaultTest extends TestCase
{
    private $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir().'/sf_secrets/test/';
        (new Filesystem())->remove($this->secretsDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->secretsDir);
    }

    public function testGenerateKeys()
    {
        $vault = new SodiumVault($this->secretsDir);

        $this->assertTrue($vault->generateKeys());
        $this->assertFileExists($this->secretsDir.'/test.encrypt.public.php');
        $this->assertFileExists($this->secretsDir.'/test.decrypt.private.php');

        $encKey = file_get_contents($this->secretsDir.'/test.encrypt.public.php');
        $decKey = file_get_contents($this->secretsDir.'/test.decrypt.private.php');

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

    public function testKeysFallback()
    {
        $vault = new SodiumVault($this->secretsDir);
        $this->assertTrue($vault->generateKeys());

        $vault->seal('foo', 'bar');
        $this->assertSame('bar', $vault->reveal('foo'));

        $vault = new SodiumVault($this->secretsDir, str_repeat('X', \SODIUM_CRYPTO_BOX_KEYPAIRBYTES));
        $this->assertSame('bar', $vault->reveal('foo'));

        unlink($this->secretsDir.'/test.decrypt.private.php');

        $vault = new SodiumVault($this->secretsDir, str_repeat('X', \SODIUM_CRYPTO_BOX_KEYPAIRBYTES));

        $this->assertNull($vault->reveal('foo'));
        $this->assertStringContainsString('cannot be revealed as the wrong decryption key was provided', $vault->getLastMessage());
    }
}
