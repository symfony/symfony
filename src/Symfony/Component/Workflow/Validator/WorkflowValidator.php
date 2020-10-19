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
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowValidator implements DefinitionValidatorInterface
{
    private $singlePlace;

    public function __construct(bool $singlePlace = false)
    {
        $this->singlePlace = $singlePlace;
    }

    public function validate(Definition $definition, $name)
    {
        // Make sure all transitions for one place has unique name.
        $places = array_fill_keys($definition->getPlaces(), []);
        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFroms() as $from) {
                if (\in_array($transition->getName(), $places[$from])) {
                    throw new InvalidDefinitionException(sprintf('All transitions for a place must have an unique name. Multiple transitions named "%s" where found for place "%s" in workflow "%s".', $transition->getName(), $from, $name));
                }
                $places[$from][] = $transition->getName();
            }
        }

        if (!$this->singlePlace) {
            return;
        }

        foreach ($definition->getTransitions() as $transition) {
            if (1 < \count($transition->getTos())) {
                throw new InvalidDefinitionException(sprintf('The marking store of workflow "%s" can not store many places. But the transition "%s" has too many output (%d). Only one is accepted.', $name, $transition->getName(), \count($transition->getTos())));
            }
        }

        $initialPlaces = $definition->getInitialPlaces();
        if (2 <= \count($initialPlaces)) {
            throw new InvalidDefinitionException(sprintf('The marking store of workflow "%s" can not store many places. But the definition has %d initial places. Only one is supported.', $name, \count($initialPlaces)));
        }
    }
}
