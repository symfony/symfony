<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

/**
 * Shared-secret message authentication (HMAC).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface MacInterface
{
    public function generateKey(int $bytes = 32): string;

    public function sign(string $message, string $key, ?string $algorithm = null): string;

    public function verify(string $tag, string $message, string $key, ?string $algorithm = null): bool;
}
