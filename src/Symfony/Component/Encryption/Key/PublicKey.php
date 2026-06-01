<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Key;

/**
 * A self-describing asymmetric public key.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class PublicKey implements KeyInterface
{
    use AsymmetricKeyTrait;

    private const MAGIC = 'SYU';

    public static function fromBytes(string $algorithm, string $purpose, string $bytes): self
    {
        return new self($algorithm, $purpose, $bytes);
    }

    public static function import(string $exported): self
    {
        [$algorithm, $purpose, $bytes] = self::parse($exported);

        return new self($algorithm, $purpose, $bytes);
    }
}
