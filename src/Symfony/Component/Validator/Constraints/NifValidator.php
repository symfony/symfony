<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that DNI/NIF spanish have specified scheme.
 *
 * @see http://es.wikipedia.org/wiki/NIF
 * @author Marcos Gómez Vilches <marcos@gesdinet.com>
 */
class NifValidator extends ConstraintValidator {
	
	/**
 	 * Validates that DNI/NIF spanish have specified scheme.
	 *
	 * @param mixed $value
	 * @param Constraint $constraint
	 */
	public function validate($value, Constraint $constraint) {
	        if (null === $value || '' === $value) {
	            return;
	        }
	        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
	        $letter = strtoupper(substr($value,8,1));
	        $nif = strotoupper(substr($value,0,8));
	        $nif = str_replace('X','0',$nif);
	        $nif = str_replace('Y','1',$nif);
	        $nif = str_replace('Z','2',$nif);
	        $nif -= intval($nif/23) * 23;
		if ($letters{$nif} != $letter) {
			$this->context->addViolation($constraint->message);
		}
	
	}

}
