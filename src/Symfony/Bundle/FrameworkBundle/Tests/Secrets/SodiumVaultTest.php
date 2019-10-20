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
        $this->assertFileExists($this->secretsDir.'/test.sodium.encrypt.public');
        $this->assertFileExists($this->secretsDir.'/test.sodium.decrypt.private');

        $encKey = file_get_contents($this->secretsDir.'/test.sodium.encrypt.public');
        $decKey = file_get_contents($this->secretsDir.'/test.sodium.decrypt.private');

        $this->assertFalse($vault->generateKeys());
        $this->assertStringEqualsFile($this->secretsDir.'/test.sodium.encrypt.public', $encKey);
        $this->assertStringEqualsFile($this->secretsDir.'/test.sodium.decrypt.private', $decKey);

        $this->assertTrue($vault->generateKeys(true));
        $this->assertStringNotEqualsFile($this->secretsDir.'/test.sodium.encrypt.public', $encKey);
        $this->assertStringNotEqualsFile($this->secretsDir.'/test.sodium.decrypt.private', $decKey);
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
}
