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
 * Cryptographic digests of arbitrary data.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface HasherInterface
{
    public function hash(string $data, ?string $algorithm = null): string;

    public function hashBase64(string $data, ?string $algorithm = null): string;

    public function raw(string $data, ?string $algorithm = null): string;
}
