<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;

final class ReadonlyInputDto
{
    #[Argument]
    public readonly string $name;

    #[MapInput]
    public readonly ReadonlyNestedInputDto $nested;

    #[Interact]
    public function interact(): void
    {
        $this->name ??= 'Bob';
    }
}

final class ReadonlyNestedInputDto
{
    #[Option]
    public string $label = 'none';
}
