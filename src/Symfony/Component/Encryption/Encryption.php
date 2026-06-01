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

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Engine\EngineSelector;

/**
 * Convenience facade exposing every capability via a lazy accessor.
 *
 * Each accessor returns the same instance on repeated calls. The engine-backed
 * capabilities share one {@see EngineSelector}. For dependency injection, you
 * can also use the individual services and their interfaces directly.
 *
 * `Comparator::equals()` is a static utility and is not exposed here.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class Encryption
{
    private ?SymmetricCipherInterface $symmetric = null;
    private ?AsymmetricCipherInterface $asymmetric = null;
    private ?SignerInterface $signer = null;
    private ?CertificateManagerInterface $certificates = null;
    private ?HasherInterface $digest = null;
    private ?MacInterface $mac = null;
    private ?PasswordHasherInterface $passwords = null;

    public function __construct(
        private readonly EngineSelector $engines = new EngineSelector(),
    ) {
    }

    public function symmetric(): SymmetricCipherInterface
    {
        return $this->symmetric ??= new SymmetricCipher($this->engines);
    }

    public function asymmetric(): AsymmetricCipherInterface
    {
        return $this->asymmetric ??= new AsymmetricCipher($this->engines);
    }

    public function signing(): SignerInterface
    {
        return $this->signer ??= new Signer($this->engines);
    }

    public function certificates(): CertificateManagerInterface
    {
        return $this->certificates ??= new CertificateManager($this->engines);
    }

    public function digest(): HasherInterface
    {
        return $this->digest ??= new Hasher();
    }

    public function mac(): MacInterface
    {
        return $this->mac ??= new Mac();
    }

    public function passwords(): PasswordHasherInterface
    {
        return $this->passwords ??= new PasswordHasher();
    }
}
