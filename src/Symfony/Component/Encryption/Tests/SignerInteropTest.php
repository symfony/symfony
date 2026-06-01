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

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibEd25519Engine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumEd25519Engine;
use Symfony\Component\Encryption\Key\PublicKey;
use Symfony\Component\Encryption\Signer;

final class SignerInteropTest extends TestCase
{
    private Signer $sodiumSigner;
    private Signer $phpseclibSigner;

    protected function setUp(): void
    {
        if (!(new SodiumEd25519Engine())->isAvailable() || !(new PhpseclibEd25519Engine())->isAvailable()) {
            self::markTestSkipped('Both Ed25519 engines are required.');
        }

        $this->sodiumSigner = new Signer(new EngineSelector(null, null, null, [new SodiumEd25519Engine()]));
        $this->phpseclibSigner = new Signer(new EngineSelector(null, null, null, [new PhpseclibEd25519Engine()]));
    }

    public function testSodiumSignsPhpseclibVerifiesDetached(): void
    {
        $pair = $this->sodiumSigner->generateKeyPair();

        $signature = $this->sodiumSigner->signDetached('cross-engine', $pair->private());

        self::assertTrue($this->phpseclibSigner->verifyDetached($signature, 'cross-engine', $pair->public()));
    }

    public function testPhpseclibSignsSodiumOpensAttached(): void
    {
        $pair = $this->phpseclibSigner->generateKeyPair();

        $signed = $this->phpseclibSigner->signAttached('cross-engine', $pair->private());

        self::assertSame('cross-engine', $this->sodiumSigner->openAttached($signed, $pair->public()));
    }

    public function testExportedSigningKeyIsPortableAcrossEngines(): void
    {
        $pair = $this->sodiumSigner->generateKeyPair();
        $signature = $this->sodiumSigner->signDetached('portable', $pair->private());

        $reloaded = PublicKey::import($pair->public()->export());

        self::assertTrue($this->phpseclibSigner->verifyDetached($signature, 'portable', $reloaded));
    }
}
