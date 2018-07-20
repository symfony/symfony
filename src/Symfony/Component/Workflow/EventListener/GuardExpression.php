<?php

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\Workflow\Transition;

class GuardExpression
{

    /**
     * @var Transition
     */
    private $transition;

    /**
     * @var string
     */
    private $expression;

    /**
     * @return Transition
     */
    public function getTransition(): Transition
    {
        return $this->transition;
    }

    /**
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * GuardConfiguration constructor.
     * @param Transition $transition
     * @param string $expression
     */
    public function __construct(Transition $transition, string $expression)
    {
        $this->transition = $transition;
        $this->expression = $expression;
    }
}