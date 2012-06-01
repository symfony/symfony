<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

class ValueMetadata extends ElementMetadata
{
/**
 * Sets the constraint(s) for this value.
 *
 * @param array $constraints
 */
	public function setConstraints(array $constraints)
    {
        $this->constraints = $constraints;
        $constraintsByGroup = array();

        array_walk($constraints, function($constraint) use (&$constraintsByGroup) {
            foreach($constraint->groups as $group){
                $constraintsByGroup[$group][] = $constraint;
            }
        });

        $this->constraintsByGroup = $constraintsByGroup;
        return $this;
    }
}
