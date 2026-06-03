<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\MarkingStore;

class SubjectWithType
{
    private string $marking;

    public function getMarking(): string
    {
        return $this->marking;
    }

    public function setMarking(string $type): void
    {
        $this->marking = $type;
    }

    public function getMarking2(): string
    {
        // Typo made on purpose!
        return $this->marking;
    }
}
