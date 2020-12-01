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

use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * A key for a specific user and specific Encryption implementation. Keys cannot
 * be shared between Encryption implementations.
 *
 * A key is always serializable.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 6.0
 */
interface KeyInterface
{
    /**
     * Creates a new KeyInterface object.
     *
     * When Alice wants share her public key with Bob, she sends him this object.
     *
     * The public key can be shared.
     *
     * @throws InvalidKeyException
     */
    public function extractPublicKey(): self;
}
