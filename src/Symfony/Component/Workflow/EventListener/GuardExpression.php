<?php

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\Workflow\Transition;

class GuardExpression
{
    private $transition;

    private $expression;

    public function getTransition(): Transition
    {
        return $this->transition;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function __construct(Transition $transition, string $expression)
    {
        $this->transition = $transition;
        $this->expression = $expression;
    }
}
