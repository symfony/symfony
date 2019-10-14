<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Secrets;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Secrets\DotenvVault;
use Symfony\Component\Dotenv\Dotenv;

class DotenvVaultTest extends TestCase
{
    private $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir().'/sf_secrets.env.test';
        @unlink($this->envFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
    }

    public function testGenerateKeys()
    {
        $vault = new DotenvVault($this->envFile);

        $this->assertFalse($vault->generateKeys());
        $this->assertSame('The dotenv vault doesn\'t encrypt secrets thus doesn\'t need keys.', $vault->getLastMessage());
    }

    public function testEncryptAndDecrypt()
    {
        $vault = new DotenvVault($this->envFile);

        $plain = "plain\ntext";

        $vault->seal('foo', $plain);

        unset($_SERVER['foo_SECRET'], $_ENV['foo_SECRET']);
        (new Dotenv(false))->load($this->envFile);

        $decrypted = $vault->reveal('foo');
        $this->assertSame($plain, $decrypted);

        $this->assertSame(['foo' => null], $vault->list());
        $this->assertSame(['foo' => $plain], $vault->list(true));

        $this->assertTrue($vault->remove('foo'));
        $this->assertFalse($vault->remove('foo'));

        unset($_SERVER['foo_SECRET'], $_ENV['foo_SECRET']);
        (new Dotenv(false))->load($this->envFile);

        $this->assertSame([], $vault->list());
    }
}
