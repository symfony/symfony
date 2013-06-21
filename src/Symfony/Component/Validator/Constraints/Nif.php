<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
* @Annotation
*/
class Nif extends Constraint
{

public $message = 'This DNI doesn´t seem to be valid.';


}
