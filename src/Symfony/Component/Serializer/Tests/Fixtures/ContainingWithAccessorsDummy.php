<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\PropertyInfo\Attribute\WithAccessors;

class ContainingWithAccessorsDummy
{
    #[WithAccessors(getter: 'retrieveName', setter: 'renameTo')]
    private string $name;

    #[WithAccessors(getter: 'currentPriority', setter: 'changePriority')]
    private int $priority;

    #[WithAccessors(getter: 'isEnabled')]
    private bool $active;

    public function __construct(string $name = '', int $priority = 0, bool $active = false)
    {
        $this->name = $name;
        $this->priority = $priority;
        $this->active = $active;
    }

    public function retrieveName(): string
    {
        return $this->name;
    }

    public function renameTo(string $name): void
    {
        $this->name = $name;
    }

    public function currentPriority(): int
    {
        return $this->priority;
    }

    public function changePriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isEnabled(): bool
    {
        return $this->active;
    }
}
