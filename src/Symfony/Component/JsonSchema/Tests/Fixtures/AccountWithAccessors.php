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

use Symfony\Component\Serializer\Attribute\Groups;

class AccountWithAccessors
{
    #[Groups(['account:internal'])]
    public string $email;
    private int $id;
    private string $password;
    #[Groups(['account:internal'])]
    private string $auditTrail;

    public function getId(): int
    {
        return $this->id;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
