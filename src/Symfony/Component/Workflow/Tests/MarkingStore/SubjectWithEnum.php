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

class SubjectWithEnum
{
    public function __construct(
        private ?TestEnum $marking = null,
        private array $context = [],
    ) {
    }

    public function getMarking(): ?TestEnum
    {
        return $this->marking;
    }

    public function setMarking(TestEnum $marking, array $context = []): void
    {
        $this->marking = $marking;
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
