<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReturnTyped
{
    public function getFoos(): array
    {
        return 'It doesn\'t respect the return type on purpose';
    }

    public function addFoo(\DateTimeImmutable $dateTime)
    {
    }

    public function removeFoo(\DateTimeImmutable $dateTime)
    {
    }

    public function setName($name): self
    {
        return 'This does not respect the return type on purpose.';
    }
}
