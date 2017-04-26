<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PropertiesInequality extends Constraint
{
    public $message = '{{ lessProperty }} should be {{ lessWord }} than {{ greaterProperty }}.';
	public $lessWord = 'less';
    public $lessValuePropertyPath = null;
    public $greaterValuePropertyPath = null;
    public $strict = true;

    /**
     * {@inheritDoc}
     */
    public function getTargets() {
        return self::CLASS_CONSTRAINT;
    }


}
