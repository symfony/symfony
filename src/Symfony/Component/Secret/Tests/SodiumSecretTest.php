<?php

declare(strict_types=1);

namespace Symfony\Component\Secret\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Secret\SodiumVault;

final class SodiumSecretTest extends TestCase
{
    private $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir().'/sf_secrets/test/';
        foreach (glob($this->secretsDir.'/*') as $file) {
            unlink($file);
        }
        @rmdir($this->secretsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->secretsDir.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->secretsDir);
    }

    public function testGenerateKeys(): void
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

    public function testEncryptAndDecrypt(): void
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
