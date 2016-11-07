<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Validator;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DefinitionInterface;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;

/**
 * If the marking can contain only one place, we should control the definition.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SinglePlaceWorkflowValidator extends WorkflowValidator
{
    public function validate(DefinitionInterface $definition, $name)
    {
        foreach ($definition->getTransitions() as $transition) {
            if (1 < count($transition->getTos())) {
                throw new InvalidDefinitionException(
                    sprintf(
                        'The marking store of workflow "%s" can not store many places. But the transition "%s" has too many output (%d). Only one is accepted.',
                        $name,
                        $transition->getName(),
                        count($transition->getTos())
                    )
                );
            }
        }

        return parent::validate($definition, $name);
    }
}
