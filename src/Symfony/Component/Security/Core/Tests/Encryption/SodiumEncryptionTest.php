<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encryption;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encryption\SodiumEncryption;
use Symfony\Component\Security\Core\Exception\MalformedCipherException;
use Symfony\Component\Security\Core\Exception\UnsupportedAlgorithmException;

class SodiumEncryptionTest extends TestCase
{
    public function testEncryption()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $cipher = $sodium->encrypt('');
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(strlen($cipher) > 10);
        $this->assertNotEquals('input', $sodium->encrypt('input'));

        $cipher = $sodium->encrypt($input = 'random_string');
        $sodium = new SodiumEncryption('different_secret');
        $this->assertNotEquals($cipher, $sodium->encrypt($input));
    }

    public function testDecryption()
    {
        $sodium = new SodiumEncryption('s3cr3t');

        $this->assertEquals($input = '', $sodium->decrypt($sodium->encrypt($input)));
        $this->assertEquals($input = 'foobar', $sodium->decrypt($sodium->encrypt($input)));
    }

    public function testDecryptionThrowsOnMalformedCipher()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $this->expectException(MalformedCipherException::class);
        $sodium->decrypt('foo');
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $this->expectException(UnsupportedAlgorithmException::class);
        $sodium->decrypt('foo.bar.baz');
    }
}
