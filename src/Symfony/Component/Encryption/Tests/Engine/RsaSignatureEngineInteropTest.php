<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslRsaSignatureEngine;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibRsaSignatureEngine;

final class RsaSignatureEngineInteropTest extends TestCase
{
    protected function setUp(): void
    {
        if (!(new OpenSslRsaSignatureEngine())->isAvailable() || !(new PhpseclibRsaSignatureEngine())->isAvailable()) {
            self::markTestSkipped('Both OpenSSL and phpseclib RSA engines are required.');
        }
    }

    public function testOpenSslSignsPhpseclibVerifies(): void
    {
        $openssl = new OpenSslRsaSignatureEngine();
        $phpseclib = new PhpseclibRsaSignatureEngine();
        [$public, $private] = $openssl->generateKeyPair();

        $signature = $openssl->sign('cross-engine', $private);

        self::assertTrue($phpseclib->verify($signature, 'cross-engine', $public));
    }

    public function testPhpseclibSignsOpenSslVerifies(): void
    {
        $openssl = new OpenSslRsaSignatureEngine();
        $phpseclib = new PhpseclibRsaSignatureEngine();
        [$public, $private] = $phpseclib->generateKeyPair();

        $signature = $phpseclib->sign('cross-engine', $private);

        self::assertTrue($openssl->verify($signature, 'cross-engine', $public));
    }
}
