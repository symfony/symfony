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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Registry
{
    private $workflows = array();

    public function __construct(array $workflows = array())
    {
        foreach ($workflows as $workflow) {
            $this->add($workflow);
        }
    }

    public function add(Workflow $workflow)
    {
        $this->workflows[] = $workflow;
    }

    public function get($object)
    {
        foreach ($this->workflows as $workflow) {
            if ($workflow->supports($object)) {
                return $workflow;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unable to find a workflow for class "%s".', get_class($object)));
    }
}
