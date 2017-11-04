<?php

namespace Symfony\Component\Workflow\SupportStrategy;

use Symfony\Component\Workflow\Workflow;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 *
 * @internal
 */
final class ClassInstanceSupportStrategy implements SupportStrategyInterface
{
    private $className;

    /**
     * @param string $className a FQCN
     */
    public function __construct($className)
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

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }
}
