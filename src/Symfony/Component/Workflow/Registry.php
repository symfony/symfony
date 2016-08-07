<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Registry
{
    private $workflows = array();

    /**
     * @param Workflow $workflow
     * @param string   $className
     */
    public function add(Workflow $workflow, $className)
    {
        $this->workflows[] = array($workflow, $className);
    }

    public function get($subject, $workflowName = null)
    {
        $matched = null;

        foreach ($this->workflows as list($workflow, $className)) {
            if ($this->supports($workflow, $className, $subject, $workflowName)) {
                if ($matched) {
                    throw new InvalidArgumentException('At least two workflows match this subject. Set a different name on each and use the second (name) argument of this method.');
                }
                $matched = $workflow;
            }
        }

        if (!$matched) {
            throw new InvalidArgumentException(sprintf('Unable to find a workflow for class "%s".', get_class($subject)));
        }

        return $matched;
    }

    private function supports(Workflow $workflow, $className, $subject, $name)
    {
        if (!$subject instanceof $className) {
            return false;
        }

        if (null === $name) {
            return true;
        }

        return $name === $workflow->getName();
    }
}
