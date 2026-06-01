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

/**
 * Password hashing for credential storage.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface PasswordHasherInterface
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;

    public function needsRehash(string $hash): bool;
}
