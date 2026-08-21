<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class MutatorPrefixesDummy
{
    public function setProp(\stdClass $prop): void
    {
    }

    public function withProp(string $prop): static
    {
        return $this;
    }

    public function addItem(\DateTime $item): void
    {
    }

    public function removeItem(\DateTimeImmutable $item): void
    {
    }
}
