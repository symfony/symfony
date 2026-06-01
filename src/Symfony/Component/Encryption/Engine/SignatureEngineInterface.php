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

namespace Symfony\Component\Encryption\Engine;

/**
 * Internal contract for a digital-signature backend.
 *
 * Produces and verifies detached signatures over raw key material.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
interface SignatureEngineInterface
{
    /**
     * @return array{0: string, 1: string} [publicKey, privateKey] raw material
     */
    public function generateKeyPair(): array;

    public function sign(string $message, string $privateKey): string;

    public function verify(string $signature, string $message, string $publicKey): bool;

    public function isAvailable(): bool;

    public function algorithm(): string;

    public function name(): string;
}
