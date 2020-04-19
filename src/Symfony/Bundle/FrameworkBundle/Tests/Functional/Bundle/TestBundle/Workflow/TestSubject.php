<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Workflow;

final class TestSubject
{
    private $marking;
    private $context;

    public function __construct($marking = null)
    {
        $this->marking = $marking;
        $this->context = [];
    }

    public function getMarking()
    {
        return $this->marking;
    }

    public function setMarking($marking, array $context = [])
    {
        $this->marking = $marking;
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function isTrue(): bool
    {
        return true;
    }
}
