<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\LoginLink;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class LoginLinkDetails
{
    public function __construct(
        private string $url,
        private \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
