<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Credentials;

/**
 * Simple username/password implementation.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class BasicAuthCredentials extends AbstractCredentials
{
    public function __construct(
        private readonly string $username,
        private readonly ?string $password = null,
    ) {}

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    #[\Override]
    protected function computeId(): string
    {
        return $this->username;
    }
}
