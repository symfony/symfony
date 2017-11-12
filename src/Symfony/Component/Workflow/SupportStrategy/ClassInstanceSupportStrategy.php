<?php

namespace Symfony\Component\Workflow\SupportStrategy;

use Symfony\Component\Workflow\Workflow;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 */
final class ClassInstanceSupportStrategy implements SupportStrategyInterface
{
    private $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Workflow $workflow, $subject)
    {
        return $subject instanceof $this->className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
