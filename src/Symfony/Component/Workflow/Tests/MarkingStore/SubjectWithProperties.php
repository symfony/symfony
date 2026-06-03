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

final class SubjectWithProperties
{
    // for type=workflow
    public array $marking;

    // for type=state_machine
    public string $place;

    private function getMarking(): array
    {
        return $this->marking;
    }
}
