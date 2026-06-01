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

namespace Symfony\Component\Encryption\Key;

/**
 * A public/private asymmetric key pair.
 *
 * Exported as the private key (the public key is derived on import), so the
 * pair round-trips through a single portable string.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class KeyPair
{
    public function __construct(
        private readonly PublicKey $public,
        private readonly PrivateKey $private,
    ) {
    }

    public function public(): PublicKey
    {
        return $this->public;
    }

    public function private(): PrivateKey
    {
        return $this->private;
    }

    public function algorithm(): string
    {
        return $this->private->algorithm();
    }

    public function export(): string
    {
        return $this->private->export();
    }

    public static function import(string $exported): self
    {
        $private = PrivateKey::import($exported);

        return new self($private->derivePublic(), $private);
    }
}
