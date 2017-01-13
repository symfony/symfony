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
 * Make sure all transitions for one place has unique name.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class UniqueTransitionNameValidator implements DefinitionValidatorInterface
{
    public function validate(Definition $definition, $name)
    {
        $places = array_fill_keys($definition->getPlaces(), []);
        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFroms() as $from) {
                if (in_array($transition->getName(), $places[$from])) {
                    throw new InvalidDefinitionException(sprintf('All transitions for a place must have an unique name. Multiple transitions named "%s" where found for place "%s" in workflow "%s".', $transition->getName(), $from, $name));
                }
                $places[$from][] = $transition->getName();
            }
        }
    }
}
