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
use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslSymmetricEngine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumSymmetricEngine;
use Symfony\Component\Encryption\Engine\AsymmetricEncryptionEngineInterface;
use Symfony\Component\Encryption\Engine\Sodium\SodiumX25519Engine;
use Symfony\Component\Encryption\Engine\CertificateEngineInterface;
use Symfony\Component\Encryption\Engine\RsaEngineInterface;
use Symfony\Component\Encryption\Engine\SignatureEngineInterface;
use Symfony\Component\Encryption\Engine\Sodium\SodiumEd25519Engine;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Exception\NoEngineAvailableException;

final class EngineSelectorTest extends TestCase
{
    public function testReturnsFirstAvailableEngine(): void
    {
        $unavailable = $this->unavailableEngine();
        $available = new SodiumSymmetricEngine();
        if (!$available->isAvailable()) {
            $available = new OpenSslSymmetricEngine();
        }
        if (!$available->isAvailable()) {
            self::markTestSkipped('Need at least one available engine.');
        }

        $selector = new EngineSelector([$unavailable, $available]);

        self::assertSame($available, $selector->symmetricEngine());
    }

    public function testThrowsWhenNoEngineIsAvailable(): void
    {
        $selector = new EngineSelector([$this->unavailableEngine()]);

        $this->expectException(NoEngineAvailableException::class);

        $selector->symmetricEngine();
    }

    public function testDefaultSelectorPrefersSodium(): void
    {
        $selector = new EngineSelector();

        $engine = $selector->symmetricEngine();

        // Sodium is bundled with PHP core, so it should win by default.
        self::assertSame('sodium', $engine->name());
    }

    public function testReturnsAsymmetricEncryptionEngineForX25519(): void
    {
        $selector = new EngineSelector();

        $engine = $selector->asymmetricEncryptionEngine('x25519');

        self::assertInstanceOf(AsymmetricEncryptionEngineInterface::class, $engine);
        self::assertSame('x25519', $engine->algorithm());
    }

    public function testThrowsForUnknownAsymmetricAlgorithm(): void
    {
        $selector = new EngineSelector();

        $this->expectException(NoEngineAvailableException::class);

        $selector->asymmetricEncryptionEngine('rsa');
    }

    public function testInjectedAsymmetricEnginesAreUsed(): void
    {
        $engine = new SodiumX25519Engine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('sodium required');
        }
        $selector = new EngineSelector(null, [$engine]);

        self::assertSame($engine, $selector->asymmetricEncryptionEngine('x25519'));
    }

    public function testReturnsRsaEngine(): void
    {
        $engine = (new EngineSelector())->rsaEngine();

        self::assertInstanceOf(RsaEngineInterface::class, $engine);
        self::assertTrue($engine->isAvailable());
    }

    public function testReturnsSignatureEngineForEd25519(): void
    {
        $engine = (new EngineSelector())->signatureEngine('ed25519');

        self::assertInstanceOf(SignatureEngineInterface::class, $engine);
        self::assertSame('ed25519', $engine->algorithm());
    }

    public function testThrowsForUnknownSignatureAlgorithm(): void
    {
        $this->expectException(NoEngineAvailableException::class);

        (new EngineSelector())->signatureEngine('dsa');
    }

    public function testReturnsSignatureEngineForRsa(): void
    {
        $engine = (new EngineSelector())->signatureEngine('rsa');

        self::assertSame('rsa', $engine->algorithm());
    }

    public function testReturnsSignatureEngineForEcdsa(): void
    {
        $engine = (new EngineSelector())->signatureEngine('ecdsa-p256');

        self::assertSame('ecdsa-p256', $engine->algorithm());
    }

    public function testReturnsCertificateEngine(): void
    {
        $engine = (new EngineSelector())->certificateEngine();

        self::assertInstanceOf(CertificateEngineInterface::class, $engine);
        self::assertTrue($engine->isAvailable());
    }

    public function testInjectedSignatureEnginesAreUsed(): void
    {
        $engine = new SodiumEd25519Engine();
        if (!$engine->isAvailable()) {
            self::markTestSkipped('sodium required');
        }
        $selector = new EngineSelector(null, null, null, [$engine]);

        self::assertSame($engine, $selector->signatureEngine('ed25519'));
    }

    private function unavailableEngine(): SymmetricEngineInterface
    {
        return new class implements SymmetricEngineInterface {
            #[\Override]
            public function encrypt(string $plaintext, string $key, string $nonce): string
            {
                return '';
            }

            #[\Override]
            public function decrypt(string $ciphertext, string $key, string $nonce): string
            {
                return '';
            }

            #[\Override]
            public function isAvailable(): bool
            {
                return false;
            }

            #[\Override]
            public function name(): string
            {
                return 'never';
            }
        };
    }
}
