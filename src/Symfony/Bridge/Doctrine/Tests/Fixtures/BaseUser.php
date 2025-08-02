<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class BaseUser
{
    private $enabled;

    public function __construct(
        private readonly int $id,

        #[Assert\NotBlank(groups: ['Registration'])]
        #[Assert\Length(min: 2, max: 120, groups: ['Registration'])]
        private readonly string $username,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
