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

class UninitializedObjectProperty
{
    public \DateTimeInterface $uninitialized;
    private \DateTimeInterface $privateUninitialized;

    public function getPrivateUninitialized(): string
    {
        return $this->privateUninitialized;
    }

    public function setPrivateUninitialized(string $privateUninitialized): void
    {
        $this->privateUninitialized = $privateUninitialized;
    }
}
