<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

use Symfony\Component\TypeInfo\Type;
use \DateTimeInterface;
use \DateTimeImmutable as DateTime;

final class DummyWithUses
{
    private DateTimeInterface $createdAt;

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getType(): Type
    {
        throw new \LogicException('Should not be called.');
    }
}
