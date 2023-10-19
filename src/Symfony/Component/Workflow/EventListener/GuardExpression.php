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
    private Transition $transition;
    private string $expression;

    public function __construct(Transition $transition, string $expression)
    {
        $this->transition = $transition;
        $this->expression = $expression;
    }

    /**
     * @return Transition
     */
    public function getTransition()
    {
        return $this->transition;
    }

    /**
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }
}
