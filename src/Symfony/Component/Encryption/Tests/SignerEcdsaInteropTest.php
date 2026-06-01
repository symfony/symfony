<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslEcdsaSignatureEngine;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibEcdsaSignatureEngine;
use Symfony\Component\Encryption\Signer;

final class SignerEcdsaInteropTest extends TestCase
{
    private Signer $opensslSigner;
    private Signer $phpseclibSigner;

    protected function setUp()
    {
        if (!(new OpenSslEcdsaSignatureEngine())->isAvailable() || !(new PhpseclibEcdsaSignatureEngine())->isAvailable()) {
            self::markTestSkipped('Both ECDSA signature engines are required.');
        }

        $this->opensslSigner = new Signer(new EngineSelector(null, null, null, [new OpenSslEcdsaSignatureEngine()]));
        $this->phpseclibSigner = new Signer(new EngineSelector(null, null, null, [new PhpseclibEcdsaSignatureEngine()]));
    }

    public function testOpenSslSignsPhpseclibVerifiesDetached()
    {
        $pair = $this->opensslSigner->generateKeyPair('ecdsa-p256');

        $signature = $this->opensslSigner->signDetached('cross-engine', $pair->private());

        self::assertTrue($this->phpseclibSigner->verifyDetached($signature, 'cross-engine', $pair->public()));
    }

    public function testPhpseclibSignsOpenSslOpensAttached()
    {
        $pair = $this->phpseclibSigner->generateKeyPair('ecdsa-p256');

        $signed = $this->phpseclibSigner->signAttached('cross-engine', $pair->private());

        self::assertSame('cross-engine', $this->opensslSigner->openAttached($signed, $pair->public()));
    }
}
