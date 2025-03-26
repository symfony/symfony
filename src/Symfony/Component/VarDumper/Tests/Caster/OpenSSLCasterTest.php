<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension openssl
 */
class OpenSSLCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testAsymmetricKey()
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 1024,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);

        if (false === $key) {
            $this->markTestSkipped('Unable to generate a key pair');
        }

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
OpenSSLAsymmetricKey {
  bits: 1024
  key: """
    -----BEGIN PUBLIC KEY-----\n
    %A
    %A
    %A
    %A
    -----END PUBLIC KEY-----\n
    """
  type: 0
}
EODUMP, $key);
    }

    public function testOpensslCsr()
    {
        $dn = [
            'countryName' => 'FR',
            'stateOrProvinceName' => 'Ile-de-France',
            'localityName' => 'Paris',
            'organizationName' => 'Symfony',
            'organizationalUnitName' => 'Security',
            'commonName' => 'symfony.com',
            'emailAddress' => 'test@symfony.com',
        ];
        $privkey = openssl_pkey_new();
        $csr = openssl_csr_new($dn, $privkey);

        if (false === $csr) {
            $this->markTestSkipped('Unable to generate a CSR');
        }

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
OpenSSLCertificateSigningRequest {
  countryName: "FR"
  stateOrProvinceName: "Ile-de-France"
  localityName: "Paris"
  organizationName: "Symfony"
  organizationalUnitName: "Security"
  commonName: "symfony.com"
  emailAddress: "test@symfony.com"
}
EODUMP, $csr);
    }
}
