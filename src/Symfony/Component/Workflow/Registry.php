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
     * @param string   $classname
     */
    public function add(Workflow $workflow, $classname)
    {
        $this->workflows[] = array($workflow, $classname);
    }

    public function get($subject, $workflowName = null)
    {
        $matched = null;

        foreach ($this->workflows as list($workflow, $classname)) {
            if ($this->supports($workflow, $classname, $subject, $workflowName)) {
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

    private function supports(Workflow $workflow, $classname, $subject, $name)
    {
        if (!$subject instanceof $classname) {
            return false;
        }

        if (null === $name) {
            return true;
        }

        return $name === $workflow->getName();
    }
}
