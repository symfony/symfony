<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

final class Subject
{
    private $marking;
    private $context;

    public function __construct($marking = null)
    {
        $this->marking = $marking;
        $this->context = [];
    }

    public function getMarking(): string|array|null
    {
        return $this->marking;
    }

    public function setMarking($marking, array $context = []): void
    {
        $this->marking = $marking;
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
