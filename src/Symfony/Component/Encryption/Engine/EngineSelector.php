<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine;

use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslCertificateEngine;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslEcdsaSignatureEngine;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslRsaSignatureEngine;
use Symfony\Component\Encryption\Engine\OpenSsl\OpenSslSymmetricEngine;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibEcdsaSignatureEngine;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibEd25519Engine;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibRsaEngine;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibRsaSignatureEngine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumEd25519Engine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumSymmetricEngine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumX25519Engine;
use Symfony\Component\Encryption\Exception\NoEngineAvailableException;

/**
 * Picks the best available backend engine per capability.
 *
 * Default preference order is Sodium then OpenSSL for symmetric work; X25519
 * asymmetric encryption is sodium-backed. Specific ordered lists may be
 * injected (used by tests to force a particular engine).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
final class EngineSelector
{
    /**
     * @var list<SymmetricEngineInterface>
     */
    private array $symmetricEngines;

    /**
     * @var list<AsymmetricEncryptionEngineInterface>
     */
    private array $asymmetricEncryptionEngines;

    private readonly RsaEngineInterface $rsaEngine;

    /**
     * @var list<SignatureEngineInterface>
     */
    private array $signatureEngines;

    private readonly CertificateEngineInterface $certificateEngine;

    /**
     * @param list<SymmetricEngineInterface>|null            $symmetricEngines
     * @param list<AsymmetricEncryptionEngineInterface>|null $asymmetricEncryptionEngines
     * @param list<SignatureEngineInterface>|null            $signatureEngines
     */
    public function __construct(
        ?array $symmetricEngines = null,
        ?array $asymmetricEncryptionEngines = null,
        ?RsaEngineInterface $rsaEngine = null,
        ?array $signatureEngines = null,
        ?CertificateEngineInterface $certificateEngine = null,
    ) {
        $this->symmetricEngines = $symmetricEngines ?? [
            new SodiumSymmetricEngine(),
            new OpenSslSymmetricEngine(),
        ];
        $this->asymmetricEncryptionEngines = $asymmetricEncryptionEngines ?? [
            new SodiumX25519Engine(),
        ];
        $this->rsaEngine = $rsaEngine ?? new PhpseclibRsaEngine();
        $this->signatureEngines = $signatureEngines ?? [
            new SodiumEd25519Engine(),
            new PhpseclibEd25519Engine(),
            new OpenSslRsaSignatureEngine(),
            new PhpseclibRsaSignatureEngine(),
            new OpenSslEcdsaSignatureEngine(),
            new PhpseclibEcdsaSignatureEngine(),
        ];
        $this->certificateEngine = $certificateEngine ?? new OpenSslCertificateEngine();
    }

    public function symmetricEngine(): SymmetricEngineInterface
    {
        foreach ($this->symmetricEngines as $engine) {
            if ($engine->isAvailable()) {
                return $engine;
            }
        }

        throw new NoEngineAvailableException('No symmetric encryption engine is available; install ext-sodium or ext-openssl with ChaCha20-Poly1305.');
    }

    public function asymmetricEncryptionEngine(string $algorithm): AsymmetricEncryptionEngineInterface
    {
        foreach ($this->asymmetricEncryptionEngines as $engine) {
            if ($engine->algorithm() === $algorithm && $engine->isAvailable()) {
                return $engine;
            }
        }

        throw new NoEngineAvailableException(\sprintf('No asymmetric encryption engine is available for algorithm "%s".', $algorithm));
    }

    public function rsaEngine(): RsaEngineInterface
    {
        if (!$this->rsaEngine->isAvailable()) {
            throw new NoEngineAvailableException('No RSA engine is available.');
        }

        return $this->rsaEngine;
    }

    public function signatureEngine(string $algorithm): SignatureEngineInterface
    {
        foreach ($this->signatureEngines as $engine) {
            if ($engine->algorithm() === $algorithm && $engine->isAvailable()) {
                return $engine;
            }
        }

        throw new NoEngineAvailableException(\sprintf('No signature engine is available for algorithm "%s".', $algorithm));
    }

    public function certificateEngine(): CertificateEngineInterface
    {
        if (!$this->certificateEngine->isAvailable()) {
            throw new NoEngineAvailableException('No certificate engine is available; ext-openssl is required for X.509 operations.');
        }

        return $this->certificateEngine;
    }
}
