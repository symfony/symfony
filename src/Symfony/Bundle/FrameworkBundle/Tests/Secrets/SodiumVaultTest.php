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

    public function testRotateWithoutPlaintextDoesNotReplaceKeys()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('FOO', 'foo');

        $privateKey = file_get_contents($this->secretsDir.'/test.decrypt.private.php');

        $this->assertFalse($vault->rotate([]));
        $this->assertStringContainsString('could not be decrypted', (string) $vault->getLastMessage());
        $this->assertStringEqualsFile($this->secretsDir.'/test.decrypt.private.php', $privateKey);
        $this->assertSame('foo', $vault->reveal('FOO'));
        $this->assertFileDoesNotExist($this->secretsDir.'/test.decrypt.private.old.php');
    }

    public function testRevealFallsBackToTheOldKeyOfAnInterruptedRotation()
    {
        $newKey = $this->interruptRotation();
        $files = $this->readFiles();

        $this->assertSame(['BAR' => 'bar', 'FOO' => 'foo'], (new SodiumVault($this->secretsDir))->list(true));
        $this->assertSame(['BAR' => 'bar', 'FOO' => 'foo'], (new SodiumVault($this->secretsDir, $newKey))->list(true));
        $this->assertSame($files, $this->readFiles());
    }

    public function testRotateResumesAnInterruptedRotationWithItsNewKey()
    {
        $newKey = $this->interruptRotation();

        $this->assertTrue((new SodiumVault($this->secretsDir))->rotate(['BAR' => 'bar', 'FOO' => 'foo']));
        $this->assertSame($newKey, include $this->secretsDir.'/test.decrypt.private.php');
        $this->assertFileDoesNotExist($this->secretsDir.'/test.decrypt.private.old.php');
        $this->assertSame(['BAR' => 'bar', 'FOO' => 'foo'], (new SodiumVault($this->secretsDir, $newKey))->list(true));
    }

    public function testRotateGeneratesNewKeysWhenTheInterruptedRotationDidNotWriteThem()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('FOO', 'foo');

        $privateKey = file_get_contents($this->secretsDir.'/test.decrypt.private.php');
        copy($this->secretsDir.'/test.decrypt.private.php', $this->secretsDir.'/test.decrypt.private.old.php');

        $this->assertTrue((new SodiumVault($this->secretsDir))->rotate(['FOO' => 'foo']));
        $this->assertStringNotEqualsFile($this->secretsDir.'/test.decrypt.private.php', $privateKey);
        $this->assertFileDoesNotExist($this->secretsDir.'/test.decrypt.private.old.php');
        $this->assertSame('foo', (new SodiumVault($this->secretsDir))->reveal('FOO'));
    }

    public function testRotateIgnoresAnIncompleteOldKey()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('FOO', 'foo');

        $privateKey = file_get_contents($this->secretsDir.'/test.decrypt.private.php');
        touch($this->secretsDir.'/test.decrypt.private.old.php');

        $this->assertSame('foo', (new SodiumVault($this->secretsDir))->reveal('FOO'));
        $this->assertTrue((new SodiumVault($this->secretsDir))->rotate(['FOO' => 'foo']));
        $this->assertStringNotEqualsFile($this->secretsDir.'/test.decrypt.private.php', $privateKey);
        $this->assertFileDoesNotExist($this->secretsDir.'/test.decrypt.private.old.php');
        $this->assertSame('foo', (new SodiumVault($this->secretsDir))->reveal('FOO'));
    }

    public function testProvidedDecryptionKeyStaysInUseWhenARotationIsInterrupted()
    {
        (new SodiumVault($this->secretsDir))->generateKeys();
        $oldKey = include $this->secretsDir.'/test.decrypt.private.php';
        $this->interruptRotation();

        $locked = new SodiumVault($this->secretsDir, $oldKey);

        $this->assertSame('bar', $locked->reveal('BAR'));
        $this->assertNull($locked->reveal('FOO'));
        $this->assertStringContainsString('wrong decryption key', (string) $locked->getLastMessage());
    }

    public function testGenerateKeysLeavesNoPublicKeyWhenThePrivateKeyCannotBeWritten()
    {
        // A directory in place of the private key makes writing it fail
        mkdir($this->secretsDir.'/test.decrypt.private.php', 0o777, true);

        try {
            @(new SodiumVault($this->secretsDir))->generateKeys();
            $this->fail('Writing the private key should have failed.');
        } catch (\ErrorException) {
        }

        rmdir($this->secretsDir.'/test.decrypt.private.php');

        $vault = new SodiumVault($this->secretsDir);
        $this->assertTrue($vault->generateKeys());
        $vault->seal('FOO', 'foo');
        $this->assertSame('foo', (new SodiumVault($this->secretsDir))->reveal('FOO'));
    }

    public function testOverridingKeysKeepsAMatchingPairWhenThePrivateKeyCannotBeWritten()
    {
        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('FOO', 'foo');

        // A directory in place of the private key makes writing it fail
        rename($this->secretsDir.'/test.decrypt.private.php', $this->secretsDir.'/private.php');
        mkdir($this->secretsDir.'/test.decrypt.private.php');

        try {
            @$vault->generateKeys(true);
            $this->fail('Writing the private key should have failed.');
        } catch (\ErrorException) {
        }

        rmdir($this->secretsDir.'/test.decrypt.private.php');
        rename($this->secretsDir.'/private.php', $this->secretsDir.'/test.decrypt.private.php');

        (new SodiumVault($this->secretsDir))->seal('BAR', 'bar');
        $this->assertSame(['BAR' => 'bar', 'FOO' => 'foo'], (new SodiumVault($this->secretsDir))->list(true));
    }

    public function testListIsReplacedInsteadOfRewritten()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows cannot replace a file that is open.');
        }

        $vault = new SodiumVault($this->secretsDir);
        $vault->generateKeys();
        $vault->seal('FOO', 'foo');

        $list = file_get_contents($file = $this->secretsDir.'/test.list.php');
        $handle = fopen($file, 'r');
        $vault->seal('BAR', 'bar');
        $this->assertSame($list, stream_get_contents($handle));
        fclose($handle);

        $list = file_get_contents($file);
        $handle = fopen($file, 'r');
        $vault->remove('FOO');
        $this->assertSame($list, stream_get_contents($handle));
        fclose($handle);

        $this->assertSame(['BAR' => null], $vault->list());
    }

    private function interruptRotation(): string
    {
        if (!is_file($this->secretsDir.'/test.decrypt.private.php')) {
            (new SodiumVault($this->secretsDir))->generateKeys();
        }

        $vault = new SodiumVault($this->secretsDir);
        $vault->seal('FOO', 'foo');
        $vault->seal('BAR', 'bar');

        $newKey = sodium_crypto_box_keypair();
        $newPublicKey = sodium_crypto_box_publickey($newKey);
        copy($this->secretsDir.'/test.decrypt.private.php', $this->secretsDir.'/test.decrypt.private.old.php');
        $this->writeEncodedFile($this->secretsDir.'/test.decrypt.private.php', $newKey);
        $this->writeEncodedFile($this->secretsDir.'/test.encrypt.public.php', $newPublicKey);
        $this->writeEncodedFile($this->secretPath('FOO'), sodium_crypto_box_seal('foo', $newPublicKey));

        return $newKey;
    }

    private function readFiles(): array
    {
        $files = [];
        foreach (glob($this->secretsDir.'/*') as $file) {
            $files[basename($file)] = file_get_contents($file);
        }

        return $files;
    }

    private function secretPath(string $name): string
    {
        return $this->secretsDir.'/test.'.$name.'.'.substr(md5($name), 0, 6).'.php';
    }

    private function writeEncodedFile(string $path, string $raw): void
    {
        file_put_contents($path, "<?php\n\nreturn \"".str_replace('%', '\x', rawurlencode($raw))."\";\n");
    }
}
