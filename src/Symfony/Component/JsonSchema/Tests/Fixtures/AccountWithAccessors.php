<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

class AccountWithAccessors
{
    public string $email;
    private string $password;

    public function __construct(
        public readonly string $id,
    ) {
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
