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
 * Provides PGP public keys from a static map of email addresses to key paths.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class InMemoryPgpPublicKeyRepository implements PgpPublicKeyRepositoryInterface
{
    /**
     * @param array<string, string> $publicKeys Public key file paths indexed by email address
     */
    public function __construct(
        private readonly array $publicKeys = [],
    ) {
    }

    public function findPublicKeyPathFor(string $email): ?string
    {
        return $this->publicKeys[$email] ?? null;
    }
}
