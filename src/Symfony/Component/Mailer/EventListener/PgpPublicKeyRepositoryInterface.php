<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\EventListener;

/**
 * Provides PGP public keys for message encryption.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface PgpPublicKeyRepositoryInterface
{
    /**
     * @return ?string The path to the PGP public key. null if not found
     */
    public function findPublicKeyPathFor(string $email): ?string;
}
