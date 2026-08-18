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
 * Provides S/MIME certificates from a static map of email addresses to certificate paths.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class InMemorySmimeCertificateRepository implements SmimeCertificateRepositoryInterface
{
    /**
     * @param array<string, string> $certificates Certificate file paths indexed by email address
     */
    public function __construct(
        private readonly array $certificates = [],
    ) {
    }

    public function findCertificatePathFor(string $email): ?string
    {
        return $this->certificates[$email] ?? null;
    }
}
