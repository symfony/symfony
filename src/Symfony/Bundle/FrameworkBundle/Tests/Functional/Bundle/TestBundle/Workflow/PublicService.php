<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Workflow;

use Symfony\Component\Workflow\Registry;

class PublicService
{
    private $registry;
    private $subject;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->subject = new TestSubject();
    }

    public function apply(string $workflowName)
    {
        $workflow = $this->registry->get($this->subject, $workflowName);
        if ($workflow->can($this->subject, 'go')) {
            $workflow->apply($this->subject, 'go');
        }
    }

    public function isApplied(string $workflowName)
    {
        $workflow = $this->registry->get($this->subject, $workflowName);

        return $workflow->getMarking($this->subject)->has('last');
    }
}
