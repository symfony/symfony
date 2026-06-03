<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Http\EventListener\CsrfProtectionListener;

/**
 * Adds automatic CSRF tokens checking capabilities to this authenticator.
 *
 * @see CsrfProtectionListener
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class CsrfTokenBadge implements BadgeInterface
{
    private bool $resolved = false;

    /**
     * @param string      $csrfTokenId An arbitrary string used to generate the value of the CSRF token.
     *                                 Using a different string for each authenticator improves its security.
     * @param string|null $csrfToken   The CSRF token presented in the request, if any
     */
    public function __construct(
        private string $csrfTokenId,
        #[\SensitiveParameter] private ?string $csrfToken,
    ) {
    }

    public function getCsrfTokenId(): string
    {
        return $this->csrfTokenId;
    }

    public function getCsrfToken(): ?string
    {
        return $this->csrfToken;
    }

    /**
     * @internal
     */
    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
