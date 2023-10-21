<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\SupportStrategy;

use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
final class InstanceOfSupportStrategy implements WorkflowSupportStrategyInterface
{
    private string $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function supports(WorkflowInterface $workflow, object $subject): bool
    {
        return $subject instanceof $this->className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
