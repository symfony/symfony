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
 */
class WorkflowValidator implements DefinitionValidatorInterface
{
    private $singlePlace;

    /**
     * @param bool $singlePlace
     */
    public function __construct($singlePlace = false)
    {
        $this->singlePlace = $singlePlace;
    }

    public function validate(Definition $definition, $name)
    {
        if (!$this->singlePlace) {
            return;
        }

        foreach ($definition->getTransitions() as $transition) {
            if (1 < count($transition->getTos())) {
                throw new InvalidDefinitionException(sprintf('The marking store of workflow "%s" can not store many places. But the transition "%s" has too many output (%d). Only one is accepted.', $name, $transition->getName(), count($transition->getTos())));
            }
        }
    }
}
