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

        self::assertTrue($vault->generateKeys());
        self::assertFileExists($this->secretsDir.'/test.encrypt.public.php');
        self::assertFileExists($this->secretsDir.'/test.decrypt.private.php');

        $encKey = file_get_contents($this->secretsDir.'/test.encrypt.public.php');
        $decKey = file_get_contents($this->secretsDir.'/test.decrypt.private.php');

        self::assertFalse($vault->generateKeys());
        self::assertStringEqualsFile($this->secretsDir.'/test.encrypt.public.php', $encKey);
        self::assertStringEqualsFile($this->secretsDir.'/test.decrypt.private.php', $decKey);

        self::assertTrue($vault->generateKeys(true));
        self::assertStringNotEqualsFile($this->secretsDir.'/test.encrypt.public.php', $encKey);
        self::assertStringNotEqualsFile($this->secretsDir.'/test.decrypt.private.php', $decKey);
    }

    public function testEncryptAndDecrypt()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();

        $plain = "plain\ntext";

        $vault->seal('foo', $plain);

        $decrypted = $vault->reveal('foo');
        self::assertSame($plain, $decrypted);

        self::assertSame(['foo' => null], $vault->list());
        self::assertSame(['foo' => $plain], $vault->list(true));

        self::assertTrue($vault->remove('foo'));
        self::assertFalse($vault->remove('foo'));

        self::assertSame([], $vault->list());
    }
}
