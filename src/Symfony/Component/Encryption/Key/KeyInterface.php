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
 * A self-describing cryptographic key.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface KeyInterface
{
    /**
     * The algorithm this key is bound to (e.g. "chacha20-poly1305-ietf").
     */
    public function algorithm(): string;

    /**
     * A versioned, portable string representation that round-trips through the
     * matching import method.
     */
    public function export(): string;
}
