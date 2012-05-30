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
		$this->constraintsByGroup = array();
		array_walk($constraints,function($constraint,$k,$self){
            foreach($constraint->groups as $group){
                $self->constraintsByGroup[$group][] = $constraint;
            }
        },$this);

        return $this;
    }
}
