<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\Workflow\Transition;

class GuardExpression
{
    public function __construct(
        private Transition $transition,
        private string $expression,
    ) {
    }

    public function getTransition(): Transition
    {
        return $this->transition;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}
